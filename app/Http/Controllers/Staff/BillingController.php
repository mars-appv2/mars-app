<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingController extends Controller
{
    /* ================= Helpers ================= */

    /** urutan preferensi tabel invoice */
    protected function invoiceTableCandidates(): array
    {
        return ['cust_invoices', 'invoices'];
    }

    /** pilih tabel untuk INDEX (yang ada saja) */
    protected function pickInvoiceTableForIndex(): ?string
    {
        foreach ($this->invoiceTableCandidates() as $t) {
            if (Schema::hasTable($t)) return $t;
        }
        return null;
    }

    /** cari invoice di tabel2 kandidat berdasarkan ID */
    protected function findInvoiceById($id): array
    {
        foreach ($this->invoiceTableCandidates() as $t) {
            if (!Schema::hasTable($t)) continue;
            $inv = DB::table($t)->where('id', $id)->first();
            if ($inv) return [$t, $inv];
        }
        return [null, null];
    }

    /** kandidat tabel payment sesuai invoice table */
    protected function paymentTablesFor(string $invoiceTable): array
    {
        if ($invoiceTable === 'cust_invoices') {
            return ['cust_payments'];
        }
        return ['payments', 'invoice_payments'];
    }

    /* ================= Actions ================= */

    public function index(Request $r)
    {
        $table = $this->pickInvoiceTableForIndex();
        $unpaid = collect();

        if ($table) {
            try {
                $cols = Schema::getColumnListing($table);
                $q = DB::table($table);

                // filter UNPAID (fleksibel)
                if (in_array('status',$cols,true)) {
                    $q->whereNotIn('status', ['paid','lunas','success','settlement']);
                } elseif (in_array('paid_at',$cols,true)) {
                    $q->whereNull('paid_at');
                } elseif (in_array('is_paid',$cols,true)) {
                    $q->where('is_paid', 0);
                }

                // pencarian opsional
                if ($r->filled('q')) {
                    $kw = trim($r->q);
                    $q->where(function($w) use($kw,$cols){
                        foreach (['number','customer_name','username','email'] as $c) {
                            if (in_array($c,$cols,true)) $w->orWhere($c,'like',"%{$kw}%");
                        }
                        if (is_numeric($kw) && in_array('id',$cols,true)) $w->orWhere('id',(int)$kw);
                    });
                }

                // urutkan
                if (in_array('created_at',$cols,true)) $q->orderByDesc('created_at');
                elseif (in_array('id',$cols,true))     $q->orderByDesc('id');

                $unpaid = $q->limit(100)->get();
            } catch (\Throwable $e) {
                \Log::error('Staff Billing index fail: '.$e->getMessage());
            }
        }

        return view('staff.billing', ['unpaid'=>$unpaid]);
    }

    public function pay(Request $r, $invoiceId)
    {
        $r->validate([
            'amount' => 'nullable|numeric|min:0',
            'method' => 'nullable|string|max:50',
            'ref_no' => 'nullable|string|max:64',
        ]);

        [$table, $inv] = $this->findInvoiceById($invoiceId);
        if (!$table || !$inv) {
            return back()->with('err','Invoice tidak ditemukan.');
        }

        $cols = Schema::getColumnListing($table);
        $paidAmount = $r->input('amount', $inv->total ?? $inv->amount ?? 0);

        DB::beginTransaction();
        try {
            // catat ke payment table kalau ada
            foreach ($this->paymentTablesFor($table) as $pt) {
                if (!Schema::hasTable($pt)) continue;
                $pcols = Schema::getColumnListing($pt);
                $payload = [
                    'invoice_id' => $inv->id,
                    'amount'     => (float)$paidAmount,
                    'method'     => $r->method,
                    'ref_no'     => $r->ref_no,
                    'paid_at'    => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                // ambil hanya kolom yang ada
                $payload = array_intersect_key($payload, array_flip($pcols)) + ['created_at'=>now(),'updated_at'=>now()];
                DB::table($pt)->insert($payload);
                break; // cukup satu tabel payment
            }

            // tandai LUNAS (fleksibel)
            $upd = [];
            if (in_array('status',$cols,true))   $upd['status']   = 'paid';
            if (in_array('paid_at',$cols,true))  $upd['paid_at']  = now();
            if (in_array('is_paid',$cols,true))  $upd['is_paid']  = 1;
            if (in_array('updated_at',$cols,true)) $upd['updated_at'] = now();
            if (empty($upd)) $upd['updated_at'] = now();

            DB::table($table)->where('id',$inv->id)->update($upd);

            DB::commit();

            // (opsional) aktifkan layanan ulang
            try {
                if (class_exists(\App\Services\Provisioner::class) && Schema::hasTable('customers')) {
                    $cid = $inv->customer_id ?? $inv->user_id ?? null;
                    if ($cid) {
                        $customer = DB::table('customers')->where('id',$cid)->first();
                        if ($customer) app(\App\Services\Provisioner::class)->activate($customer);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Provisioner after pay warn: '.$e->getMessage());
            }

            return back()->with('ok','Pembayaran dicatat, invoice LUNAS.');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Staff Billing pay fail: '.$e->getMessage());
            return back()->with('err','Gagal menyimpan pembayaran: '.$e->getMessage());
        }
    }
}
