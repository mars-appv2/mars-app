<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Mikrotik;
use App\Services\RouterOSService;

class PaymentUiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['midtransNotify']);
    }

    /* =========================
     * Halaman Pembayaran
     * ========================= */
    public function index(Request $r)
    {
        $devices = Mikrotik::forUser(auth()->user())->orderBy('name')->get(['id','name','host']);
        if ($devices->isEmpty()) {
            $devices = DB::table('mikrotiks')->select('id','name','host')->orderBy('name')->get();
        }

        $mikId  = (int) $r->query('mikrotik_id', 0);
        $status = $r->query('status','unpaid');
        $q      = trim((string)$r->query('q',''));

        $rows = DB::table('invoices as i')
            ->leftJoin('subscriptions as s','s.id','=','i.subscription_id')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->leftJoin('mikrotiks as m1','m1.id','=','i.mikrotik_id')
            ->leftJoin('mikrotiks as m2','m2.id','=','s.mikrotik_id')
            ->when($mikId>0, fn($qq)=>$qq->whereRaw('COALESCE(i.mikrotik_id, s.mikrotik_id) = ?',[$mikId]))
            ->when($status!=='', fn($qq)=>$qq->where('i.status',$status))
            ->when($q!=='', function($qq) use($q){
                $qq->where(function($w) use($q){
                    $w->where('i.number','like',"%{$q}%")->orWhere('s.username','like',"%{$q}%");
                });
            })
            ->orderBy('i.id','desc')
            ->select([
                'i.id','i.number','i.status','i.period','i.due_date','i.created_at',
                DB::raw('COALESCE(i.mikrotik_id, s.mikrotik_id) as mikrotik_id'),
                DB::raw('COALESCE(m1.name, m2.name, "—") as mikrotik_name'),
                DB::raw('COALESCE(i.customer_name, s.username, "—") as username'),
                DB::raw('COALESCE(i.total, i.amount, COALESCE(p.price_month,p.price,0)) as total_effective'),
            ])
            ->limit(1000)->get();

        // Midtrans env/config display
        $mid_server = (string) env('MIDTRANS_SERVER_KEY','');
        $mid_client = (string) env('MIDTRANS_CLIENT_KEY','');
        $mid_prod   = in_array((string) env('MIDTRANS_IS_PRODUCTION','0'), ['1','true','on','yes'], true);

        // Data rekening / e-wallet dari settings (fallback ENV)
        $bank = [
            'bca' => [
                'name' => (string) (DB::table('settings')->where('key','bank_bca_name')->value('value') ?? env('BANK_BCA_NAME','')),
                'no'   => (string) (DB::table('settings')->where('key','bank_bca_no')->value('value')   ?? env('BANK_BCA_NO','')),
            ],
            'bri' => [
                'name' => (string) (DB::table('settings')->where('key','bank_bri_name')->value('value') ?? env('BANK_BRI_NAME','')),
                'no'   => (string) (DB::table('settings')->where('key','bank_bri_no')->value('value')   ?? env('BANK_BRI_NO','')),
            ],
            'mandiri' => [
                'name' => (string) (DB::table('settings')->where('key','bank_mandiri_name')->value('value') ?? env('BANK_MANDIRI_NAME','')),
                'no'   => (string) (DB::table('settings')->where('key','bank_mandiri_no')->value('value')   ?? env('BANK_MANDIRI_NO','')),
            ],
        ];

        return view('billing.payments', [
            'devices'    => $devices,
            'mikrotikId' => $mikId ?: '',
            'status'     => $status,
            'q'          => $q,
            'rows'       => $rows,
            'hasRows'    => $rows->count() > 0,
            'mid_server' => $mid_server,
            'mid_client' => $mid_client,
            'mid_prod'   => $mid_prod,
            'bank'       => $bank,
        ]);
    }

    /* =========================
     * Bulk: tandai lunas manual (tanpa upload)
     * ========================= */
    public function manualPay(Request $r)
    {
        $ids = $this->parseIds($r->input('ids'));
        if (empty($ids)) return back()->with('err','Tidak ada invoice terpilih.');

        $now = now();
        $updated = 0;

        foreach ($ids as $id) {
            $inv = DB::table('invoices')->where('id',$id)->first(['id','number','status','amount','total','subscription_id']);
            if (!$inv) continue;

            $total = (int) ($inv->total ?? $inv->amount ?? 0);
            if ($total <= 0) continue; // tolak nominal 0

            DB::table('invoices')->where('id',$inv->id)->update([
                'status'   => 'paid',
                'paid_at'  => $now,
                'updated_at' => $now,
            ]);
            $updated++;

            // log ke payments jika tabel ada
            if (Schema::hasTable('payments')) {
                DB::table('payments')->insert([
                    'invoice_id' => $inv->id,
                    'method'     => 'manual',
                    'amount'     => $total,
                    'status'     => 'paid',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // auto-unisolir
            $this->unisolateByInvoiceId($inv->id);
        }

        return back()->with('ok', "Pembayaran manual: {$updated} invoice ditandai lunas.");
    }

    /* =========================
     * Manual Bank/E-Wallet: unggah bukti & optional tandai lunas
     * ========================= */
    public function manualBank(Request $r)
    {
        $d = $r->validate([
            'invoice_id'   => 'required|integer',
            'method'       => 'required|string',
            'amount'       => 'nullable|numeric|min:0',
            'mark_paid'    => 'nullable',
            'proof'        => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);
        $inv = DB::table('invoices')->where('id',(int)$d['invoice_id'])->first();
        if (!$inv) return back()->with('err','Invoice tidak ditemukan.');

        $allowed = ['bank_bca','bank_bri','bank_mandiri','ovo','dana','gopay'];
        if (!in_array($d['method'],$allowed,true)) {
            return back()->with('err','Metode tidak dikenal.');
        }

        $nominal = (int) ($d['amount'] ?? ($inv->total ?? $inv->amount ?? 0));
        if ($nominal <= 0) return back()->with('err','Nominal 0 tidak dapat diproses.');

        // simpan bukti
        $proofPath = null;
        if ($r->hasFile('proof')) {
            $fname = 'proof_'.$inv->number.'_'.time().'.'.$r->file('proof')->getClientOriginalExtension();
            $proofPath = $r->file('proof')->storeAs('public/payments/proofs', $fname);
        }

        $now = now();
        if (Schema::hasTable('payments')) {
            DB::table('payments')->insert([
                'invoice_id' => $inv->id,
                'method'     => $d['method'],
                'amount'     => $nominal,
                'status'     => $r->has('mark_paid') ? 'paid' : 'pending',
                'proof_url'  => $proofPath ? Storage::url($proofPath) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($r->has('mark_paid')) {
            DB::table('invoices')->where('id',$inv->id)->update([
                'status'    => 'paid',
                'paid_at'   => $now,
                'updated_at'=> $now,
            ]);
            // auto-unisolir
            $this->unisolateByInvoiceId($inv->id);
            return back()->with('ok','Pembayaran dicatat & invoice ditandai lunas.');
        }

        return back()->with('ok','Pembayaran dicatat sebagai "pending". Tandai lunas setelah verifikasi.');
    }

    /* =========================
     * Gateway: buat transaksi Midtrans Snap (pilih channel)
     * ========================= */
    public function gatewayCreate(Request $r)
    {
        $id = (int) $r->input('invoice_id');
        $channel = (string) $r->input('channel','all'); // all|bca_va|bri_va|mandiri_bill|gopay|ovo|dana
        $inv = DB::table('invoices')->where('id',$id)->first();
        if (!$inv) return back()->with('err','Invoice tidak ditemukan.');

        $gross = (int) ($inv->total ?? $inv->amount ?? 0);
        if ($gross <= 0) return back()->with('err','Nominal 0 tidak dapat dibayar.');

        $serverKey = (string) env('MIDTRANS_SERVER_KEY','');
        if ($serverKey === '') return back()->with('err','MIDTRANS_SERVER_KEY belum diisi.');

        $isProd = in_array((string) env('MIDTRANS_IS_PRODUCTION','0'), ['1','true','on','yes'], true);
        $base   = $isProd ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';

        $payload = [
            'transaction_details' => [
                'order_id'     => $inv->number,
                'gross_amount' => $gross,
            ],
            'customer_details' => [
                'first_name' => $inv->customer_name ?: 'Customer',
            ],
            'callbacks' => [
                'finish' => url('/billing/invoices/'.$inv->id),
            ],
            'credit_card' => [ 'secure' => true ],
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit'       => 'minutes',
                'duration'   => 60,
            ],
        ];

        // batasi channel jika dipilih
        $enabled = null;
        switch ($channel) {
            case 'bca_va':        $enabled = ['bca_va']; break;
            case 'bri_va':        $enabled = ['bri_va']; break;
            case 'mandiri_bill':  $enabled = ['echannel']; break; // Mandiri bill via echannel
            case 'gopay':         $enabled = ['gopay']; break;
            case 'ovo':           $enabled = ['ovo']; break;
            case 'dana':          $enabled = ['dana']; break;
            default: $enabled = null; // all
        }
        if ($enabled) $payload['enabled_payments'] = $enabled;

        $snap = $this->midCall("{$base}/snap/v1/transactions", $serverKey, $payload);
        if (!$snap || empty($snap['token']) || empty($snap['redirect_url'])) {
            return back()->with('err','Gagal membuat transaksi Midtrans.');
        }

        if (Schema::hasColumn('invoices','gateway_token')) {
            DB::table('invoices')->where('id',$inv->id)->update([
                'gateway_token' => $snap['token'],
                'updated_at'    => now(),
            ]);
        }

        return back()->with('ok', 'Snap dibuat. Silakan klik bayar di bawah.')
                     ->with('snap_url', $snap['redirect_url']);
    }

    /* =========================
     * Webhook Midtrans (notify)
     * ========================= */
    public function midtransNotify(Request $r)
    {
        $json = $r->getContent();
        $data = json_decode($json, true);
        if (!is_array($data)) return response('Bad Request', 400);

        $serverKey = (string) env('MIDTRANS_SERVER_KEY','');
        if ($serverKey === '') return response('No server key', 400);

        // verifikasi signature_key
        $order_id   = (string) ($data['order_id'] ?? '');
        $statusCode = (string) ($data['status_code'] ?? '');
        $grossAmt   = (string) ($data['gross_amount'] ?? '0');
        $sign       = (string) ($data['signature_key'] ?? '');
        $calc       = hash('sha512', $order_id.$statusCode.$grossAmt.$serverKey);

        if (!hash_equals($calc, $sign)) {
            return response('Invalid signature', 403);
        }

        $transaction = (string) ($data['transaction_status'] ?? '');
        $fraud       = (string) ($data['fraud_status'] ?? 'accept');

        if (in_array($transaction, ['settlement','capture'], true) && $fraud === 'accept') {
            $inv = DB::table('invoices')->where('number',$order_id)->first(['id','status','total','amount']);
            if ($inv) {
                $now = now();
                DB::table('invoices')->where('id',$inv->id)->update([
                    'status'    => 'paid',
                    'paid_at'   => $now,
                    'updated_at'=> $now,
                ]);

                if (Schema::hasTable('payments')) {
                    DB::table('payments')->insert([
                        'invoice_id' => $inv->id,
                        'method'     => 'midtrans',
                        'amount'     => (int) ($inv->total ?? $inv->amount ?? 0),
                        'status'     => 'paid',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->unisolateByInvoiceId($inv->id);
            }
        }

        return response('OK', 200);
    }

    /* =========================
     * Helpers
     * ========================= */

    private function parseIds($raw): array
    {
        if (is_array($raw)) return array_values(array_unique(array_map('intval',$raw)));
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw==='') return [];
            $parts = preg_split('/[,\s]+/', $raw);
            return array_values(array_unique(array_map('intval', $parts)));
        }
        return [];
    }

    private function midCall(string $url, string $serverKey, array $payload): ?array
    {
        $ch = curl_init($url);
        $auth = base64_encode($serverKey.':');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic '.$auth,
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        if ($res === false) { curl_close($ch); return null; }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) return json_decode($res,true);
        return null;
    }

    private function unisolateByInvoiceId(int $invoiceId): void
    {
        $row = DB::table('invoices as i')
            ->leftJoin('subscriptions as s','s.id','=','i.subscription_id')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->leftJoin('mikrotiks as m','m.id','=','s.mikrotik_id')
            ->where('i.id',$invoiceId)
            ->select([
                's.username','s.mikrotik_id',
                DB::raw('COALESCE(p.name, NULL) as plan_name'),
            ])->first();
        if (!$row || !$row->username) return;

        $plan = $row->plan_name ?: $this->radiusPlanFor($row->username);
        $this->unisolateUsername($row->username, (int)$row->mikrotik_id, $plan);
    }

    private function radiusPlanFor(string $username): ?string
    {
        try {
            return DB::connection('radius')->table('radusergroup')
                ->where('username',$username)->value('groupname');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function unisolateUsername(string $username, ?int $mikId, ?string $plan): void
    {
        // 1) Hapus Reject di RADIUS
        try {
            DB::connection('radius')->table('radcheck')
                ->where('username',$username)
                ->where('attribute','Auth-Type')
                ->where('op',':=')
                ->where('value','Reject')
                ->delete();
        } catch (\Throwable $e) {}

        // 2) Set groupname sesuai plan (fallback: default)
        $usePlan = $plan ?: (string) env('BILLING_DEFAULT_PROFILE','default');
        try {
            DB::connection('radius')->table('radusergroup')->updateOrInsert(
                ['username'=>$username],
                ['groupname'=>$usePlan, 'priority'=>1]
            );
        } catch (\Throwable $e) {}

        // 3) Kembalikan PPP profile di router kalau ada device
        if ($mikId) {
            $m = DB::table('mikrotiks')->where('id',$mikId)->first(['id','name','host','username','password','port']);
            if ($m) {
                try {
                    $svc = new RouterOSService((object)$m);
                    $svc->pppSet($username, ['profile'=>$usePlan]);
                } catch (\Throwable $e) {
                    // diamkan
                }
            }
        }

        // 4) Pastikan subscription aktif
        DB::table('subscriptions')->where('username',$username)->update(['status'=>'active','updated_at'=>now()]);
    }
}
