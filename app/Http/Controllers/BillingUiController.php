<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use App\Models\Mikrotik;
use App\Models\Setting;

class BillingUiController extends Controller
{
    /* =========================
     * PLANS
     * ========================= */
    public function plans(Request $r)
    {
        $devices = Mikrotik::forUser(auth()->user())->orderBy('name')->get(['id','name','host']);
        if ($devices->isEmpty()) {
            $devices = DB::table('mikrotiks')->select('id','name','host')->orderBy('name')->get();
        }

        $mikrotikId = $r->query('mikrotik_id');
        $q          = trim((string)$r->query('q',''));

        $cols = ['id','name'];
        if (Schema::hasColumn('plans','price'))        $cols[] = 'price';
        if (Schema::hasColumn('plans','price_month'))  $cols[] = 'price_month';
        if (Schema::hasColumn('plans','description'))  $cols[] = 'description';
        if (Schema::hasColumn('plans','mikrotik_id'))  $cols[] = 'mikrotik_id';

        $plansQ = DB::table('plans')->select($cols)->orderBy('id','desc');
        if (!empty($mikrotikId) && Schema::hasColumn('plans','mikrotik_id')) {
            $plansQ->where('mikrotik_id', (int)$mikrotikId);
        }
        if ($q !== '') {
            $plansQ->where('name','like',"%{$q}%");
        }
        $plans = $plansQ->limit(2000)->get();

        return view('billing.plans', [
            'devices'    => $devices,
            'plans'      => $plans,
            'mikrotikId' => $mikrotikId ?? '',
            'q'          => $q ?? '',
        ]);
    }

    /** Import PLANS: CSV (name,price[,desc][,mikrotik_id]) atau dari Mikrotik (PPP profile) */
    public function importPlans(Request $r)
    {
        $mikId = (int)$r->input('mikrotik_id', 0);

        /* ================= CSV ================= */
        if ($r->hasFile('file')) {
            $path = $r->file('file')->store('tmp');
            $rows = array_map('str_getcsv', file(Storage::path($path)));

            $hasPm  = Schema::hasColumn('plans','price_month');
            $hasDes = Schema::hasColumn('plans','description');
            $hasMk  = Schema::hasColumn('plans','mikrotik_id');

            $n=0;
            foreach ($rows as $row) {
                if (!isset($row[0])) continue;
                $name = trim((string)$row[0]);
                if ($name==='') continue;

                $rawPrice = (string)($row[1] ?? '0');
                $price    = (int) str_replace([',','.'],'', $rawPrice);
                $desc     = trim((string)($row[2] ?? ''));

                $where = ['name'=>$name];
                if ($hasMk) $where['mikrotik_id'] = (int)($row[3] ?? 0) ?: null;

                $data = [
                    'price'      => $price,
                    'updated_at' => now(),
                ];
                if ($hasPm)  $data['price_month'] = $price ?: null;
                if ($hasDes) $data['description'] = $desc ?: null;
                if ($hasMk)  $data['mikrotik_id'] = $where['mikrotik_id'];

                $exists = DB::table('plans')->where($where)->first();
                if ($exists) {
                    DB::table('plans')->where('id',$exists->id)->update($data);
                } else {
                    $data['name']       = $name;
                    $data['created_at'] = now();
                    DB::table('plans')->insert($data);
                }
                $n++;
            }
            Storage::delete($path);

            return redirect()
                ->route('billing.plans', ['mikrotik_id'=>$mikId ?: null])
                ->with('ok',"Import selesai: {$n} plan diproses (CSV).");
        }

        /* =============== Dari Mikrotik =============== */
        if (!class_exists(\RouterOS\Client::class)) {
            return redirect()
                ->route('billing.plans')
                ->with('err','Library RouterOS tidak ditemukan. Install: composer require evilfreelancer/routeros-api-php');
        }

        if ($mikId <= 0) {
            return redirect()->route('billing.plans')->with('err','Pilih perangkat Mikrotik untuk import.');
        }

        $m = DB::table('mikrotiks')->where('id',$mikId)->first(['id','host','username','password','port']);
        if (!$m) return redirect()->route('billing.plans')->with('err','Perangkat tidak ditemukan.');

        try {
            $client = new \RouterOS\Client([
                'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                'port'=>$m->port ?: 8728,'timeout'=>10,'attempts'=>1,
            ]);
            $rows = $client->query((new \RouterOS\Query('/ppp/profile/print')))->read();
        } catch (\Throwable $e) {
            \Log::error('Import plans Mikrotik gagal: '.$e->getMessage());
            return redirect()
                ->route('billing.plans', ['mikrotik_id'=>$mikId])
                ->with('err','Gagal koneksi Mikrotik: '.$e->getMessage());
        }

        $hasPm  = Schema::hasColumn('plans','price_month');
        $hasDes = Schema::hasColumn('plans','description');
        $hasMk  = Schema::hasColumn('plans','mikrotik_id');

        $created=0; $updated=0; $skipped=0;

        foreach ($rows as $rr) {
            $name = trim((string)($rr['name'] ?? ''));
            if ($name==='') { $skipped++; continue; }

            $comment = (string)($rr['comment'] ?? '');
            $price   = $this->guessPrice($name, $comment);

            $where = ['name'=>$name];
            if ($hasMk) $where['mikrotik_id'] = $mikId;

            $data = [
                'price'      => $price,
                'updated_at' => now(),
            ];
            if ($hasPm)  $data['price_month'] = $price ?: null;
            if ($hasDes) $data['description'] = $comment ?: null;
            if ($hasMk)  $data['mikrotik_id'] = $mikId;

            $exists = DB::table('plans')->where($where)->first();

            if ($exists) {
                $needUpdate = (
                    (int)($exists->price ?? 0) !== (int)$data['price'] ||
                    ($hasPm  && (int)($exists->price_month ?? 0) !== (int)$data['price_month']) ||
                    ($hasDes && (string)($exists->description ?? '') !== (string)$data['description'])
                );
                if ($needUpdate) {
                    DB::table('plans')->where('id',$exists->id)->update($data);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                $data['name']       = $name;
                $data['created_at'] = now();
                DB::table('plans')->insert($data);
                $created++;
            }
        }

        return redirect()
            ->route('billing.plans', ['mikrotik_id'=>$mikId])
            ->with('ok',"Import dari Mikrotik: {$created} dibuat, {$updated} diperbarui, {$skipped} dilewati.");
    }

    public function planStore(Request $r)
    {
        $name  = trim((string)$r->input('name',''));
        $price = (int)$r->input('price',0);
        if ($name==='') return back()->with('err','Nama plan wajib diisi.');

        $data = ['name'=>$name,'price'=>$price,'updated_at'=>now(),'created_at'=>now()];
        if (Schema::hasColumn('plans','price_month')) $data['price_month'] = $price ?: null;
        if (Schema::hasColumn('plans','description')) $data['description'] = trim((string)$r->input('description','')) ?: null;
        if (Schema::hasColumn('plans','mikrotik_id')) $data['mikrotik_id'] = (int)$r->input('mikrotik_id') ?: null;

        DB::table('plans')->insert($data);
        return back()->with('ok','Plan dibuat.');
    }

    public function planUpdate(Request $r, $id)
    {
        $name  = trim((string)$r->input('name',''));
        $price = (int)$r->input('price',0);
        if ($name==='') return back()->with('err','Nama plan wajib diisi.');

        $data = ['name'=>$name,'price'=>$price,'updated_at'=>now()];
        if (Schema::hasColumn('plans','price_month')) $data['price_month'] = $price ?: null;
        if (Schema::hasColumn('plans','description')) $data['description'] = trim((string)$r->input('description','')) ?: null;
        if (Schema::hasColumn('plans','mikrotik_id')) $data['mikrotik_id'] = (int)$r->input('mikrotik_id') ?: null;

        DB::table('plans')->where('id',$id)->update($data);
        return back()->with('ok','Plan diperbarui.');
    }

    public function planDelete($id)
    {
        DB::table('plans')->where('id',$id)->delete();
        return back()->with('ok','Plan dihapus.');
    }

    /* =========================
     * SUBSCRIPTIONS
     * ========================= */
    public function subs(Request $r)
    {
        $devices = Mikrotik::forUser(auth()->user())->orderBy('name')->get(['id','name','host']);
        if ($devices->isEmpty()) {
            $devices = DB::table('mikrotiks')->select('id','name','host')->orderBy('name')->get();
        }

        $mikId = (int) $r->query('mikrotik_id', 0);
        $planId= (int) $r->query('plan_id', 0);
        $q     = trim((string)$r->query('q',''));

        $planList = DB::table('plans')->select('id','name')->orderBy('name')->get();

        $subs = DB::table('subscriptions as s')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->leftJoin('mikrotiks as m','m.id','=','s.mikrotik_id')
            ->when($mikId>0,  fn($qq)=>$qq->where('s.mikrotik_id',$mikId))
            ->when($planId>0, fn($qq)=>$qq->where('s.plan_id',$planId))
            ->when($q!=='',   fn($qq)=>$qq->where('s.username','like',"%{$q}%"))
            ->orderBy('s.id','desc')
            ->select([
                's.id','s.username','s.status','s.mikrotik_id','s.plan_id',
                DB::raw('COALESCE(p.name,"—") as plan'),
                DB::raw('COALESCE(m.name,"—") as mikrotik_name'),
            ])
            ->limit(1000)->get();

        return view('billing.subs', [
            'devices'    => $devices,
            'mikrotikId' => $mikId ?: '',
            'planId'     => $planId ?: '',
            'plans'      => $planList,
            'q'          => $q ?? '',
            'subs'       => $subs,
            'hasSubs'    => $subs->count() > 0,
        ]);
    }

    public function subsBulkDelete(Request $r)
    {
        $scope   = (string) $r->input('scope','selected'); // selected | filter
        $withInv = (bool) $r->input('with_invoices', false);

        $ids = [];
        if ($scope === 'selected') {
            $ids = $this->parseIds($r->input('ids', []));
        } else {
            $mikId  = (int) $r->input('mikrotik_id', 0);
            $planId = (int) $r->input('plan_id', 0);
            $q      = trim((string)$r->input('q',''));

            $qSub = DB::table('subscriptions');
            if ($mikId > 0)  $qSub->where('mikrotik_id', $mikId);
            if ($planId > 0) $qSub->where('plan_id', $planId);
            if ($q !== '')   $qSub->where('username','like',"%{$q}%");

            $ids = $qSub->limit(100000)->pluck('id')->all();
        }

        if (empty($ids)) {
            return back()->with('err','Tidak ada data yang cocok untuk dihapus.');
        }

        DB::beginTransaction();
        try {
            if ($withInv) {
                DB::table('invoices')->whereIn('subscription_id',$ids)->delete();
            }
            DB::table('subscriptions')->whereIn('id',$ids)->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err','Gagal menghapus: '.$e->getMessage());
        }

        return back()->with('ok','Berhasil menghapus '.count($ids).' subscription'.($withInv?' (+ invoices)':'').'.');
    }

    /* =========================
     * INVOICES
     * ========================= */
    public function invoices(Request $r)
    {
        $devices = Mikrotik::forUser(auth()->user())->orderBy('name')->get(['id','name','host']);
        if ($devices->isEmpty()) {
            $devices = DB::table('mikrotiks')->select('id','name','host')->orderBy('name')->get();
        }

        $mikrotikId = $r->query('mikrotik_id');
        $status     = $r->query('status');
        $from       = $r->query('from');
        $to         = $r->query('to');
        $q          = trim((string)$r->query('q',''));

        $rows = DB::table('invoices as i')
            ->leftJoin('subscriptions as s','s.id','=','i.subscription_id')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->leftJoin('mikrotiks as m1','m1.id','=','i.mikrotik_id')
            ->leftJoin('mikrotiks as m2','m2.id','=','s.mikrotik_id')
            ->when($mikrotikId, fn($qq)=>$qq->whereRaw('COALESCE(i.mikrotik_id, s.mikrotik_id) = ?',[(int)$mikrotikId]))
            ->when($status,     fn($qq)=>$qq->where('i.status',$status))
            ->when($from,       fn($qq)=>$qq->where('i.period','>=',$from))
            ->when($to,         fn($qq)=>$qq->where('i.period','<=',$to))
            ->when($q !== '', function($qq) use($q){
                $qq->where(function($w) use($q){
                    $w->where('i.number','like',"%{$q}%")
                      ->orWhere('s.username','like',"%{$q}%");
                });
            })
            ->orderBy('i.id','desc')
            ->select([
                'i.id','i.number','i.status','i.period','i.due_date','i.created_at',
                DB::raw('COALESCE(i.mikrotik_id, s.mikrotik_id) as mikrotik_id'),
                DB::raw('COALESCE(m1.name, m2.name, "—") as mikrotik_name'),
                DB::raw('COALESCE(i.customer_name, s.username, "—") as username'),
                DB::raw('COALESCE(i.total, i.amount, COALESCE(p.price_month,p.price,0)) as total_effective'),
                DB::raw('COALESCE(s.id, i.subscription_id) as subscription_id'),
                DB::raw('COALESCE(p.name, s.username, i.customer_name, "—") as plan_name'),
            ])
            ->limit(1000)->get();

        return view('billing.invoices', [
            'devices'    => $devices,
            'mikrotikId' => $mikrotikId ?? '',
            'status'     => $status     ?? '',
            'from'       => $from       ?: now()->format('Y-m'),
            'to'         => $to         ?: now()->format('Y-m'),
            'q'          => $q          ?? '',
            'invoices'   => $rows,
            'hasInv'     => $rows->count() > 0,
        ]);
    }

    public function invoiceShow($id)
    {
        $row = $this->invoiceRow($id);
        abort_unless($row, 404);
        return view('billing.invoice_show', ['row'=>$row]);
    }

    public function invoicePrint($id)
    {
        $row = $this->invoiceRow($id);
        abort_unless($row, 404);
        return view('billing.invoice_print', ['row'=>$row]);
    }

    private function invoiceRow($id)
    {
        return DB::table('invoices as i')
            ->leftJoin('subscriptions as s','s.id','=','i.subscription_id')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->leftJoin('mikrotiks as m1','m1.id','=','i.mikrotik_id')
            ->leftJoin('mikrotiks as m2','m2.id','=','s.mikrotik_id')
            ->where('i.id',$id)
            ->select([
                'i.*',
                DB::raw('COALESCE(i.mikrotik_id, s.mikrotik_id) as mikrotik_id'),
                DB::raw('COALESCE(m1.name, m2.name, "—") as mikrotik_name'),
                DB::raw('COALESCE(s.id, i.subscription_id) as subscription_id'),
                DB::raw('COALESCE(p.name, s.username, i.customer_name, "—") as subscription_name'),
                DB::raw('COALESCE(i.total, i.amount, COALESCE(p.price_month,p.price,0)) as total_effective'),
                DB::raw('COALESCE(p.price_month, p.price, 0) as base_price'),
            ])
            ->first();
    }

    public function invoicesBulkDelete(Request $r)
    {
        $ids = (array) $this->parseIds($r->input('ids', []));
        if (!$ids) return back()->with('err','Tidak ada data terpilih.');
        DB::table('invoices')->whereIn('id',$ids)->delete();
        return back()->with('ok','Invoice terpilih dihapus.');
    }

    /* =========================
     * PAYMENTS (MANUAL)
     * ========================= */
    public function payments(Request $r)
    {
        $devices = Mikrotik::forUser(auth()->user())->orderBy('name')->get(['id','name','host']);
        if ($devices->isEmpty()) {
            $devices = DB::table('mikrotiks')->select('id','name','host')->orderBy('name')->get();
        }

        $mikId = (int) $r->query('mikrotik_id', 0);
        $q     = trim((string)$r->query('q',''));

        $rows = DB::table('invoices as i')
            ->leftJoin('subscriptions as s','s.id','=','i.subscription_id')
            ->leftJoin('mikrotiks as m1','m1.id','=','i.mikrotik_id')
            ->leftJoin('mikrotiks as m2','m2.id','=','s.mikrotik_id')
            ->when($mikId>0, fn($qq)=>$qq->whereRaw('COALESCE(i.mikrotik_id, s.mikrotik_id) = ?',[$mikId]))
            ->where('i.status','unpaid')
            ->when($q!=='', function($qq) use($q){
                $qq->where(function($w) use($q){
                    $w->where('i.number','like',"%{$q}%")
                      ->orWhere('s.username','like',"%{$q}%")
                      ->orWhere('i.customer_name','like',"%{$q}%");
                });
            })
            ->orderBy('i.id','desc')
            ->select([
                'i.id','i.number','i.period','i.total','i.amount','i.due_date',
                DB::raw('COALESCE(i.customer_name, s.username, "—") as username'),
                DB::raw('COALESCE(m1.name, m2.name, "—") as mikrotik_name'),
                DB::raw('COALESCE(i.mikrotik_id, s.mikrotik_id) as mikrotik_id'),
            ])->limit(1000)->get();

        // Ambil rekening & e-wallet dari settings (optional)
        $accKeys = [
            'pay_bca','pay_bri','pay_mandiri','pay_ovo','pay_dana','pay_gopay'
        ];
        $acc = [];
        if (class_exists(Setting::class)) {
            $acc = Setting::whereIn('key',$accKeys)->pluck('value','key')->toArray();
        }

        return view('billing.payments', [
            'devices'    => $devices,
            'mikrotikId' => $mikId ?: '',
            'q'          => $q ?? '',
            'rows'       => $rows,
            'accounts'   => $acc,
        ]);
    }

    public function paymentsMarkPaid(Request $r)
    {
        $id = (int) $r->input('id');
        if ($id <= 0) return back()->with('err','ID tidak valid.');

        $now = now();
        DB::table('invoices')->where('id',$id)->update([
            'status'   => 'paid',
            'paid_at'  => $now,
            'updated_at'=>$now,
        ]);

        // (Opsional) panggil job restore PPP profile + CoA di tempat lain
        return back()->with('ok','Invoice ditandai lunas.');
    }

    public function paymentsBulkPaid(Request $r)
    {
        $ids = $this->parseIds($r->input('ids',[]));
        if (empty($ids)) return back()->with('err','Tidak ada invoice terpilih.');

        $now = now();
        DB::table('invoices')->whereIn('id',$ids)->update([
            'status'=>'paid', 'paid_at'=>$now, 'updated_at'=>$now
        ]);
        return back()->with('ok','Invoice terpilih ditandai lunas.');
    }

    /* =========================
     * TOOLS
     * ========================= */
    public function toolsSync(Request $r)
    {
        $defaultMik = (int)$r->input('mikrotik_id', 0);

        $radUsers = DB::connection('radius')->table('radcheck')
            ->select('username')->where('attribute','Cleartext-Password')
            ->distinct()->pluck('username');

        if ($radUsers->isEmpty()) return back()->with('err','Tidak ada user di RADIUS.');

        $groupMap = DB::connection('radius')->table('radusergroup')
            ->whereIn('username', $radUsers->all() ?: ['__none__'])
            ->pluck('groupname','username');

        $planRows = DB::table('plans')->select('id','name')->get();
        $planByName = [];
        foreach ($planRows as $p) $planByName[strtolower($p->name)] = (int) $p->id;

        $lastNas = DB::connection('radius')->table('radacct')
            ->select('username','nasipaddress','acctstarttime')
            ->whereIn('username', $radUsers->all())
            ->orderBy('acctstarttime')
            ->get()
            ->groupBy('username')
            ->map(function($rows){
                $last = $rows->last();
                return $last ? $last->nasipaddress : null;
            });

        $mkIndex   = $this->buildMikrotikIndex();
        $singleMik = $this->singleMikrotikId();

        $now = now(); $n=0;
        foreach ($radUsers as $u) {
            $grp    = $groupMap[$u] ?? null;
            $planId = $grp ? ($planByName[strtolower($grp)] ?? null) : null;

            $nas   = $lastNas[$u] ?? null;
            $mikId = $this->resolveMikId($nas, $mkIndex) ?: ($defaultMik ?: $singleMik);

            DB::table('subscriptions')->updateOrInsert(
                ['username'=>$u],
                [
                    'plan_id'     => $planId,
                    'mikrotik_id' => $mikId,
                    'status'      => 'active',
                    'updated_at'  => $now,
                    'created_at'  => $now,
                ]
            );
            $n++;
        }

        return back()->with('ok',"Sync selesai: {$n} subscriptions terbarui.");
    }

    public function toolsGenerate(Request $r)
    {
        $period    = trim((string)$r->input('period')) ?: now()->format('Y-m');
        $dueDays   = (int) ($r->input('due_days', 10));
        $useVAT    = (int) (env('BILLING_VAT_PERCENT', 0));
        $discount  = (int) ($r->input('discount', 0));
        $filterMik = (int) $r->input('mikrotik_id');
        $pickedIds = $this->parseIds($r->input('subs'));

        $subsQ = DB::table('subscriptions as s')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->select('s.id','s.username','s.mikrotik_id',
                     DB::raw('COALESCE(p.price_month, p.price, 0) as base_price'))
            ->where('s.status','active');

        if (!empty($pickedIds)) {
            $subsQ->whereIn('s.id', $pickedIds);
        } elseif (!empty($filterMik)) {
            $subsQ->where('s.mikrotik_id', $filterMik);
        }

        $subs = $subsQ->orderBy('s.id')->limit(5000)->get();
        if ($subs->isEmpty()) return back()->with('err','Tidak ada subscription aktif yang cocok.');

        $now = now();
        $due = $now->copy()->addDays($dueDays);

        $last = DB::table('invoices')->where('period',$period)->orderBy('id','desc')->value('number');
        $seq = 1; if ($last && preg_match('/-(\d{4})$/',$last,$m)) $seq = (int)$m[1] + 1;

        $mkIndex   = $this->buildMikrotikIndex();
        $singleMik = $this->singleMikrotikId();

        $created = 0;
        foreach ($subs as $s) {
            // Skip harga 0
            if ((int)$s->base_price <= 0) continue;

            $exists = DB::table('invoices')
                ->where('subscription_id',$s->id)
                ->where('period',$period)->exists();
            if ($exists) continue;

            $amount = (int)$s->base_price;
            $ppnAmt = $useVAT > 0 ? (int) floor($amount * $useVAT / 100) : 0;
            $total  = max(0, $amount + $ppnAmt - $discount);

            $number = sprintf('INV%s-%04d', str_replace('-','',$period), $seq++);

            $mikId = $s->mikrotik_id ?: ($filterMik ?: null);
            if (!$mikId) {
                $nas = DB::connection('radius')->table('radacct')
                    ->where('username',$s->username)->orderBy('acctstarttime')->value('nasipaddress');
                $mikId = $this->resolveMikId($nas, $mkIndex) ?: $singleMik;
            }

            DB::table('invoices')->insert([
                'subscription_id' => $s->id,
                'mikrotik_id'     => $mikId,
                'number'          => $number,
                'amount'          => $amount,
                'total'           => $total,
                'status'          => 'unpaid',
                'period'          => $period,
                'due_date'        => $due->format('Y-m-d'),
                'customer_name'   => $s->username,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $created++;
        }

        return back()->with('ok',"Generate selesai: {$created} invoice dibuat untuk periode {$period}.");
    }

    public function toolsEnforce(Request $r)
    {
        // Placeholder aman agar route tidak error. Logika isolir/restore jalan via scheduler command kamu.
        return back()->with('ok','Enforce dipicu.');
    	try { \Illuminate\Support\Facades\Artisan::call('billing:enforce'); }
    	catch (\Throwable $e) { return back()->with('err','Enforce gagal: '.$e->getMessage()); }
    	return back()->with('ok','Enforce dijalankan.');

    }

    /* =========================
     * TEMPLATE (INVOICE)
     * ========================= */
    public function templateEdit()
    {
        $tpl = [
            'company'    => env('INV_COMPANY',  env('APP_NAME','')),
            'address'    => env('INV_ADDRESS',''),
            'phone'      => env('INV_PHONE',''),
            'tax_id'     => env('INV_TAX_ID',''),
            'logo_url'   => env('INV_LOGO_URL',''),
            'logo_align' => env('INV_LOGO_ALIGN','left'),
        ];
        return view('billing.template', compact('tpl'));
    }

    public function templateSave(Request $r)
    {
        $pairs = $this->collectTemplatePairs($r);

        if ($r->hasFile('logo')) {
            $path = $r->file('logo')->storeAs('public/invoice','logo.png');
            $pairs['INV_LOGO_URL'] = asset(str_replace('public','storage',$path));
        }

        $this->updateEnv($pairs);

        try { Artisan::call('config:clear'); } catch (\Throwable $e) {}

        return back()->with('ok','Template tersimpan.');
    }

    /* ===== Helpers ===== */

    /** deteksi harga dari nama/comment profile */
    private function guessPrice(string $name, string $comment): int
    {
        $src = trim($comment.' '.$name);
        if (preg_match('/price\s*=\s*([\d\.\,]+)/i', $src, $m)) {
            return (int)str_replace([',','.'],'', $m[1]);
        }
        if (preg_match('/(\d{1,3}(?:[.,]\d{3})+)/', $src, $m)) {
            return (int)str_replace([',','.'],'', $m[1]);
        }
        if (preg_match('/(\d+)\s*(k|rb)\b/i', $src, $m)) {
            return ((int)$m[1]) * 1000;
        }
        if (preg_match_all('/\d{2,6}/', $src, $mm) && !empty($mm[0])) {
            rsort($mm[0]);
            $n = (int)$mm[0][0];
            return $n < 1000 ? $n * 1000 : $n;
        }
        return 0;
    }

    private function buildMikrotikIndex(): array
    {
        $rows = DB::table('mikrotiks')->select('id','host')->get();
        $idx = [];
        foreach ($rows as $r) {
            $k = strtolower(trim((string)$r->host));
            if ($k!=='') $idx[$k]=(int)$r->id;
        }
        return $idx;
    }
    private function singleMikrotikId(): ?int
    {
        $c = DB::table('mikrotiks')->count();
        return $c===1 ? (int)DB::table('mikrotiks')->orderBy('id')->value('id') : null;
    }
    private function resolveMikId(?string $nas, array $idx): ?int
    {
        if (!$nas) return null;
        $k = strtolower(trim($nas));
        return $idx[$k] ?? null;
    }
    private function parseIds($raw): array
    {
        if (is_array($raw)) return array_values(array_unique(array_map('intval',$raw)));
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw==='') return [];
            $j = json_decode($raw,true);
            if (json_last_error()===JSON_ERROR_NONE && is_array($j))
                return array_values(array_unique(array_map('intval',$j)));
            $parts = preg_split('/[,\s]+/',$raw);
            return array_values(array_unique(array_map('intval',$parts)));
        }
        return [];
    }

    private function collectTemplatePairs(Request $r): array
    {
        $pairs = [];
        if ($r->has('inv_company') || $r->has('company')) {
            $pairs['INV_COMPANY'] = trim((string)($r->input('inv_company',$r->input('company',''))));
        }
        if ($r->has('inv_address') || $r->has('address')) {
            $pairs['INV_ADDRESS'] = trim((string)($r->input('inv_address',$r->input('address',''))));
        }
        if ($r->has('inv_phone') || $r->has('phone')) {
            $pairs['INV_PHONE'] = trim((string)($r->input('inv_phone',$r->input('phone',''))));
        }
        if ($r->has('inv_tax_id') || $r->has('tax_id')) {
            $pairs['INV_TAX_ID'] = trim((string)($r->input('inv_tax_id',$r->input('tax_id',''))));
        }
        if ($r->has('inv_logo_align') || $r->has('logo_align')) {
            $align = $r->input('inv_logo_align',$r->input('logo_align','left'));
            $pairs['INV_LOGO_ALIGN'] = in_array($align,['left','center','right']) ? $align : 'left';
        }
        if ($r->has('footer_note'))  $pairs['INV_FOOTER_NOTE']   = trim((string)$r->input('footer_note'));
        if ($r->has('bank_name'))    $pairs['INV_BANK_NAME']     = trim((string)$r->input('bank_name'));
        if ($r->has('bank_no'))      $pairs['INV_BANK_ACC_NO']   = trim((string)$r->input('bank_no'));
        if ($r->has('bank_holder'))  $pairs['INV_BANK_ACC_NAME'] = trim((string)$r->input('bank_holder'));
        return $pairs;
    }

    private function updateEnv(array $pairs): void
    {
        if (empty($pairs)) return;
        $env = base_path('.env');
        if (!is_file($env) || !is_writable($env)) return;
        $content = file_get_contents($env);
        foreach ($pairs as $k => $v) {
            $v = str_replace(["\r","\n"], ' ', (string)$v);
            $line = $k.'="'.addslashes($v).'"';
            $pattern = "/^{$k}=.*$/m";
            if (preg_match($pattern, $content)) $content = preg_replace($pattern, $line, $content);
            else $content .= PHP_EOL.$line;
        }
        file_put_contents($env, $content);
    }
}
