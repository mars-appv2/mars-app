<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Mikrotik;
use App\Services\RouterOSService;
use Carbon\Carbon;

class RadiusUiController extends Controller
{
    /** ===== LIST USERS + FILTER + PLANS (ROS + DB fallback) ===== */
    public function users(Request $r)
    {
        $user = auth()->user();

        $devices = Mikrotik::forUser($user)
            ->where('radius_enabled', 1)
            ->orderBy('name')
            ->get(['id','name','host']);

        $sel = (int) $r->query('mikrotik_id', 0);
        if ($sel && !$devices->pluck('id')->contains($sel)) abort(403);

        // Kumpulkan nama PPP profile dari device terpilih / semua
        $plansFromRos = [];
        $targets = $sel ? $devices->where('id', $sel) : $devices;
        foreach ($targets as $m) {
            try {
                $svc = new RouterOSService($m);
                foreach ($svc->pppProfiles() as $row) {
                    $nm = $row['name'] ?? null;
                    if ($nm) $plansFromRos[$nm] = true;
                }
            } catch (\Throwable $e) {
                // diamkan; fallback DB
            }
        }

        // Augment dari tabel plans (jika ada)
        $plansFromDb = DB::table('plans')
            ->when($sel > 0, fn($q) => $q->where('mikrotik_id', $sel))
            ->pluck('name')
            ->all();

        $plans = array_values(array_unique(array_merge(array_keys($plansFromRos), $plansFromDb)));
        sort($plans);

        // Users dari DB RADIUS
        $q   = trim((string) $r->query('q',''));
        $rad = DB::connection('radius');

        $usersQ = $rad->table('radcheck')
            ->select('username')
            ->where('attribute','Cleartext-Password');
        if ($q !== '') $usersQ->where('username','like',"%{$q}%");

        $users = $usersQ->orderBy('username')->limit(10000)->get();

        // username -> plan (radusergroup)
        $groupMap = $rad->table('radusergroup')
            ->whereIn('username', $users->pluck('username')->all() ?: ['__none__'])
            ->pluck('groupname','username');

        // username -> active/inactive (Auth-Type := Reject)
        $rejectMap = $rad->table('radcheck')
            ->select('username')
            ->where('attribute','Auth-Type')
            ->where('op',':=')
            ->where('value','Reject')
            ->whereIn('username', $users->pluck('username')->all() ?: ['__none__'])
            ->pluck('username')
            ->flip();

        $statusMap = [];
        foreach ($users as $u) {
            $statusMap[$u->username] = $rejectMap->has($u->username) ? 'inactive' : 'active';
        }

        return view('radius.users', [
            'devices'   => $devices,
            'sel'       => $sel,
            'q'         => $q,
            'users'     => $users,
            'groupMap'  => $groupMap,
            'statusMap' => $statusMap,
            'plans'     => $plans,
        ]);
    }

    /** Tambah / upsert user (RADIUS) + sinkron best-effort ke Mikrotik */
    public function usersStore(Request $r)
    {
        $r->validate([
            'username'    => 'required|string|max:255',
            'password'    => 'required|string|max:255',
            'plan'        => 'nullable|string|max:255',
            'mikrotik_id' => 'nullable|integer',
        ]);

        $mikId = (int) $r->input('mikrotik_id', 0);
        $allowed = Mikrotik::forUser(auth()->user())
            ->where('radius_enabled',1)->pluck('id');
        if ($mikId && !$allowed->contains($mikId)) abort(403);

        $rad = DB::connection('radius');

        DB::beginTransaction();
        try {
            // Upsert password
            $row = $rad->table('radcheck')
                ->where('username',$r->username)
                ->where('attribute','Cleartext-Password')
                ->first();

            if ($row) {
                $rad->table('radcheck')->where('id',$row->id)->update(['value'=>$r->password]);
            } else {
                $rad->table('radcheck')->insert([
                    'username'=>$r->username,'attribute'=>'Cleartext-Password','op'=>':=','value'=>$r->password,
                ]);
            }

            // Plan
            if ($r->filled('plan')) {
                $rad->table('radusergroup')->updateOrInsert(
                    ['username'=>$r->username],
                    ['groupname'=>$r->plan,'priority'=>1]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err','Gagal simpan user: '.$e->getMessage());
        }

        // === Sinkron ke Mikrotik (best-effort) ===
        $mk = $this->resolveMikrotikForUser($mikId, $r->username);
        if ($mk) {
            try {
                $this->routerUpsert($mk, $r->username, $r->password, $r->input('plan') ?: null, null);
            } catch (\Throwable $e) {
                // Jangan gagalkan; hanya info
                return back()->with('ok','User disimpan (router sync gagal: '.$e->getMessage().')');
            }
        }

        return back()->with('ok','User disimpan');
    }

    /** Update password (upsert) + sinkron password ke Mikrotik kalau bisa di-resolve */
    public function usersUpdatePassword(Request $r)
    {
        $r->validate([
            'username'=>'required|string|max:255',
            'password'=>'required|string|max:255',
            'mikrotik_id' => 'nullable|integer',
        ]);

        $rad = DB::connection('radius');

        DB::beginTransaction();
        try {
            $row = $rad->table('radcheck')
                ->where('username',$r->username)
                ->where('attribute','Cleartext-Password')
                ->first();

            if ($row) {
                $rad->table('radcheck')->where('id',$row->id)->update(['value'=>$r->password]);
            } else {
                $rad->table('radcheck')->insert([
                    'username'=>$r->username,'attribute'=>'Cleartext-Password','op'=>':=','value'=>$r->password,
                ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err','Gagal update password: '.$e->getMessage());
        }

        $mk = $this->resolveMikrotikForUser((int)$r->input('mikrotik_id',0), $r->username);
        if ($mk) {
            try {
                $this->routerUpsert($mk, $r->username, $r->password, null, null);
            } catch (\Throwable $e) {
                return back()->with('ok','Password diperbarui (router sync gagal: '.$e->getMessage().')');
            }
        }

        return back()->with('ok','Password diperbarui');
    }
    public function usersPassword(Request $r) { return $this->usersUpdatePassword($r); }
    public function usersPass(Request $r)     { return $this->usersUpdatePassword($r); }

    /** Update plan (radusergroup) + sinkron profile di Mikrotik bila profil tersedia */
    public function usersUpdatePlan(Request $r)
    {
        $r->validate([
            'username'=>'required|string|max:255',
            'plan'=>'nullable|string|max:255',
            'mikrotik_id' => 'nullable|integer',
        ]);

        $rad = DB::connection('radius');

        DB::beginTransaction();
        try {
            if ($r->filled('plan')) {
                $rad->table('radusergroup')->updateOrInsert(
                    ['username'=>$r->username],
                    ['groupname'=>$r->plan,'priority'=>1]
                );
            } else {
                $rad->table('radusergroup')->where('username',$r->username)->delete();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err','Gagal update plan: '.$e->getMessage());
        }

        $mk = $this->resolveMikrotikForUser((int)$r->input('mikrotik_id',0), $r->username);
        if ($mk && $r->filled('plan')) {
            try {
                // hanya push jika profil ada di device
                $svc = new RouterOSService($mk);
                $names = [];
                foreach ($svc->pppProfiles() as $row) {
                    if (!empty($row['name'])) $names[$row['name']] = true;
                }
                if (isset($names[$r->plan])) {
                    $this->routerUpsert($mk, $r->username, null, $r->plan, null);
                }
            } catch (\Throwable $e) {
                return back()->with('ok','Plan diperbarui (router sync gagal: '.$e->getMessage().')');
            }
        }

        return back()->with('ok','Plan diperbarui');
    }
    public function usersPlan(Request $r) { return $this->usersUpdatePlan($r); }

    /** Aktif/nonaktif: RADIUS Reject + toggle disabled di PPP secret (best-effort) */
    public function usersUpdateStatus(Request $r)
    {
        $r->validate([
            'username'=>'required|string|max:255',
            'action'=>'required|in:activate,deactivate',
            'mikrotik_id' => 'nullable|integer',
        ]);

        $rad = DB::connection('radius');

        DB::beginTransaction();
        try {
            if ($r->action === 'deactivate') {
                $exists = $rad->table('radcheck')->where([
                    ['username','=',$r->username], ['attribute','=','Auth-Type'],
                ])->first();

                if ($exists) {
                    $rad->table('radcheck')->where('id',$exists->id)->update(['op'=>':=','value'=>'Reject']);
                } else {
                    $rad->table('radcheck')->insert([
                        'username'=>$r->username,'attribute'=>'Auth-Type','op'=>':=','value'=>'Reject'
                    ]);
                }
            } else {
                $rad->table('radcheck')->where([
                    ['username','=',$r->username],
                    ['attribute','=','Auth-Type'],
                    ['op',':='], ['value','=','Reject'],
                ])->delete();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err','Gagal ubah status: '.$e->getMessage());
        }

        // sinkron disabled=yes/no di secret PPP
        $mk = $this->resolveMikrotikForUser((int)$r->input('mikrotik_id',0), $r->username);
        if ($mk) {
            try {
                $this->routerUpsert($mk, $r->username, null, null, $r->action);
            } catch (\Throwable $e) {
                return back()->with('ok','Status diperbarui (router sync gagal: '.$e->getMessage().')');
            }
        }

        return back()->with('ok','Status diperbarui');
    }
    public function usersStatus(Request $r) { return $this->usersUpdateStatus($r); }
    public function usersToggle(Request $r) { return $this->usersUpdateStatus($r); }

    /** Hapus user (RADIUS) — opsional hapus di router TIDAK dilakukan agar aman */
    public function usersDelete(Request $r)
    {
        $r->validate(['username'=>'required|string|max:255']);

        $rad = DB::connection('radius');

        DB::beginTransaction();
        try {
            $rad->table('radcheck')->where('username',$r->username)->delete();
            $rad->table('radusergroup')->where('username',$r->username)->delete();
            if ($rad->getPdo()->query("SHOW TABLES LIKE 'radreply'")->rowCount()) {
                $rad->table('radreply')->where('username',$r->username)->delete();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('err','Gagal hapus user: '.$e->getMessage());
        }

        return back()->with('ok','User dihapus');
    }

    /** Import users dari Mikrotik ke RADIUS (+plans +subs +invoice jika price>0) */
    public function importUsers(Request $r)
    {
        $r->validate(['mikrotik_id'=>'required|integer']);
        $m = Mikrotik::forUser(auth()->user())->findOrFail((int)$r->mikrotik_id);

        $svc = new RouterOSService($m);
        $secrets = $svc->pppSecrets(); // [['name'=>, 'password'=>, 'profile'=>], ...]

        $rad = DB::connection('radius');
        $now = now();

        $planPriceByName = DB::table('plans')->where('mikrotik_id',$m->id)->pluck('price','name');

        $created = 0; $updated = 0;

        foreach ($secrets as $s) {
            $u = trim((string)($s['name'] ?? ''));
            if ($u === '') continue;

            $pwd = (string)($s['password'] ?? '');
            $profile = (string)($s['profile'] ?? '');

            // radcheck
            $row = $rad->table('radcheck')->where('username',$u)
                        ->where('attribute','Cleartext-Password')->first();
            if ($row) {
                if ($pwd !== '') {
                    $rad->table('radcheck')->where('id',$row->id)->update(['value'=>$pwd]);
                    $updated++;
                }
            } else {
                $rad->table('radcheck')->insert([
                    'username'=>$u, 'attribute'=>'Cleartext-Password', 'op'=>':=', 'value'=>($pwd!==''?$pwd:bin2hex(random_bytes(4))),
                ]);
                $created++;
            }

            // group
            if ($profile !== '') {
                $rad->table('radusergroup')->updateOrInsert(
                    ['username'=>$u],
                    ['groupname'=>$profile, 'priority'=>1]
                );
                DB::table('plans')->updateOrInsert(
                    ['name'=>$profile, 'mikrotik_id'=>$m->id],
                    ['price'=> (int) ($planPriceByName[$profile] ?? 0)]
                );
            }

            // subscriptions + invoice (amount>0)
            if (Schema::hasTable('subscriptions')) {
                $subData = [
                    'username'    => $u,
                    'mikrotik_id' => $m->id,
                    'status'      => 'active',
                    'updated_at'  => $now,
                ];
                if (Schema::hasColumn('subscriptions','created_at')) $subData['created_at'] = $now;
                if (Schema::hasColumn('subscriptions','plan_id') && Schema::hasTable('plans')) {
                    $planId = DB::table('plans')->where(['name'=>$profile,'mikrotik_id'=>$m->id])->value('id');
                    if ($planId) $subData['plan_id'] = $planId;
                }
                DB::table('subscriptions')->updateOrInsert(['username'=>$u], $subData);

                $price = (int)($planPriceByName[$profile] ?? 0);
                if (Schema::hasTable('invoices') && $price > 0) {
                    $this->ensureInvoiceFor($u, $price, $m->id, $now);
                }
            }
        }

        return back()->with('ok', "Import selesai. New: $created, Updated: $updated, Perangkat: {$m->name}");
    }

    /** Import offline CSV/TXT (tidak sentuh Mikrotik) */
    public function usersImportFromFile(Request $r)
    {
        $r->validate([
            'mikrotik_id'  => 'nullable|integer',
            'with_subs'    => 'nullable|in:0,1',
            'with_invoice' => 'nullable|in:0,1',
            'file'         => 'nullable|file',
            'payload'      => 'nullable|string',
        ]);

        $defaultMik = (int) $r->input('mikrotik_id', 0);
        $withSubs   = (int) $r->input('with_subs', 1);
        $withInv    = (int) $r->input('with_invoice', 0);

        $lines = [];
        if ($r->hasFile('file')) {
            $raw = file($r->file('file')->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = is_array($raw) ? $raw : [];
        }
        $payload = trim((string)$r->input('payload',''));
        if ($payload !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $payload) as $ln) {
                $ln = trim($ln);
                if ($ln !== '') $lines[] = $ln;
            }
        }

        if (empty($lines)) return back()->with('err','Tidak ada data untuk diimport.');

        $rad = DB::connection('radius');
        $now = now();
        $created = 0; $updated = 0; $skipped = 0;

        foreach ($lines as $ln) {
            if ($ln === '' || preg_match('/^\s*[#;]/', $ln)) { $skipped++; continue; }

            $parts = preg_split('/[,\:\;\s]+/', $ln);
            if (!$parts || !isset($parts[0])) { $skipped++; continue; }

            $u    = trim((string)($parts[0] ?? ''));
            $pwd  = trim((string)($parts[1] ?? ''));
            $plan = trim((string)($parts[2] ?? ''));
            $stat = strtolower(trim((string)($parts[3] ?? 'active')));
            $mik  = (int) ($parts[4] ?? 0);
            if ($mik <= 0) $mik = $defaultMik ?: null;

            if ($u === '') { $skipped++; continue; }

            if ($pwd === '') $pwd = env('RADIUS_IMPORTED_DEFAULT_PASSWORD', bin2hex(random_bytes(4)));

            // radcheck
            $row = $rad->table('radcheck')->where('username',$u)
                        ->where('attribute','Cleartext-Password')->first();
            if ($row) {
                $rad->table('radcheck')->where('id',$row->id)->update(['value'=>$pwd]);
                $updated++;
            } else {
                $rad->table('radcheck')->insert([
                    'username'=>$u, 'attribute'=>'Cleartext-Password', 'op'=>':=', 'value'=>$pwd,
                ]);
                $created++;
            }

            // plan
            if ($plan !== '') {
                $rad->table('radusergroup')->updateOrInsert(
                    ['username'=>$u],
                    ['groupname'=>$plan,'priority'=>1]
                );
            } else {
                $rad->table('radusergroup')->where('username',$u)->delete();
            }

            // status
            if ($stat === 'inactive' || $stat === 'nonaktif') {
                $exists = $rad->table('radcheck')->where([
                    ['username','=',$u], ['attribute','=','Auth-Type'],
                ])->first();
                if ($exists) {
                    $rad->table('radcheck')->where('id',$exists->id)->update(['op'=>':=','value'=>'Reject']);
                } else {
                    $rad->table('radcheck')->insert([
                        'username'=>$u,'attribute'=>'Auth-Type','op'=>':=','value'=>'Reject'
                    ]);
                }
            } else {
                $rad->table('radcheck')->where([
                    ['username','=',$u], ['attribute','=','Auth-Type'],
                    ['op',':='], ['value','=','Reject'],
                ])->delete();
            }

            // subscriptions + invoice (opsional, amount>0 saja)
            if ($withSubs && Schema::hasTable('subscriptions')) {
                $subData = [
                    'username'    => $u,
                    'status'      => 'active',
                    'updated_at'  => $now,
                ];
                if ($mik) $subData['mikrotik_id'] = $mik;
                if (Schema::hasColumn('subscriptions','created_at')) $subData['created_at'] = $now;
                if ($plan !== '' && Schema::hasTable('plans') && Schema::hasColumn('subscriptions','plan_id')) {
                    $planId = DB::table('plans')->where(['name'=>$plan])->value('id');
                    if ($planId) $subData['plan_id'] = $planId;
                }
                DB::table('subscriptions')->updateOrInsert(['username'=>$u], $subData);

                if ($withInv && Schema::hasTable('invoices')) {
                    $price = 0;
                    if ($plan !== '' && Schema::hasTable('plans')) {
                        $p = DB::table('plans')->where('name',$plan)->select('price','price_month')->first();
                        if ($p) $price = (int) ($p->price_month ?? $p->price ?? 0);
                    }
                    if ($price > 0) $this->ensureInvoiceFor($u, $price, $mik, $now);
                }
            }
        }

        return back()->with('ok',"Import CSV/TXT selesai. New: {$created}, Updated: {$updated}, Skipped: {$skipped}");
    }

    /** Pastikan ada invoice UNPAID periode berjalan (amount>0) */
    private function ensureInvoiceFor(string $username, int $amount, ?int $mikId, $now): void
    {
        if ($amount <= 0) return;

        $period = now()->format('Y-m');
        $exists = DB::table('invoices')
            ->when(Schema::hasColumn('invoices','subscription_id'), function($q) use ($username){
                $sid = DB::table('subscriptions')->where('username',$username)->value('id');
                if ($sid) $q->where('subscription_id',$sid);
            }, function($q) use ($username){
                if (Schema::hasColumn('invoices','pppoe_username')) $q->where('pppoe_username',$username);
                if (Schema::hasColumn('invoices','customer_name'))  $q->orWhere('customer_name',$username);
            })
            ->where('period',$period)
            ->where('status','unpaid')
            ->exists();

        if ($exists) return;

        $last = DB::table('invoices')->where('period',$period)->orderBy('id','desc')->value('number');
        $seq = 1; if ($last && preg_match('/-(\d{4})$/',$last,$m)) $seq = (int)$m[1] + 1;
        $number = sprintf('INV%s-%04d', str_replace('-','',$period), $seq);

        $dueDays = (int) env('BILLING_DUE_IN_DAYS', 10);
        $dueDate = now()->copy()->addDays($dueDays)->format('Y-m-d');

        $row = [
            'number'        => $number,
            'status'        => 'unpaid',
            'period'        => $period,
            'amount'        => $amount,
            'total'         => $amount,
            'customer_name' => $username,
            'due_date'      => $dueDate,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
        if ($mikId && Schema::hasColumn('invoices','mikrotik_id')) $row['mikrotik_id'] = $mikId;
        if (Schema::hasColumn('invoices','pppoe_username')) $row['pppoe_username'] = $username;
        if (Schema::hasColumn('invoices','subscription_id')) {
            $sid = DB::table('subscriptions')->where('username',$username)->value('id');
            if ($sid) $row['subscription_id'] = $sid;
        }

        DB::table('invoices')->insert($row);
    }

    /** ===== SESSIONS: radacct + fallback aktif dari Mikrotik ===== */
    public function sessions(Request $r)
    {
        $user = auth()->user();

        $devices = Mikrotik::forUser($user)
            ->where('radius_enabled',1)
            ->orderBy('name')
            ->get(['id','name','host','username','password','port']);

        $sel = (int)$r->query('mikrotik_id',0);
        if ($sel && !$devices->pluck('id')->contains($sel)) abort(403);

        $q = trim((string)$r->query('q',''));

        $rad = DB::connection('radius');
        $radRows = $rad->table('radacct')
            ->select([
                'username','nasipaddress','framedipaddress',
                'acctstarttime','acctsessiontime','acctinputoctets','acctoutputoctets',
                'callingstationid'
            ])
            ->when($q!=='' , fn($qq)=>$qq->where('username','like',"%{$q}%"))
            ->when($sel>0, function($qq) use($devices,$sel){
                $host = optional($devices->firstWhere('id',$sel))->host;
                if ($host) $qq->where('nasipaddress',$host);
            })
            ->whereNull('acctstoptime')
            ->orderByDesc('acctstarttime')
            ->limit(10000)
            ->get()
            ->map(function($x){
                $x->source = 'radius';
                return $x;
            });

        $sess = collect($radRows);

        if ($sel > 0) {
            $mk = $devices->firstWhere('id',$sel);
            if ($mk) {
                try {
                    $svc = new RouterOSService($mk);
                    $active = $svc->pppActive();

                    foreach ($active as $a) {
                        $uptime = $a['uptime'] ?? '0s';
                        $secs = $this->uptimeToSeconds($uptime);
                        $start = Carbon::now()->subSeconds($secs);

                        $row = (object)[
                            'username'         => $a['name'] ?? '',
                            'nasipaddress'     => $mk->host,
                            'framedipaddress'  => $a['address'] ?? null,
                            'acctstarttime'    => $start->toDateTimeString(),
                            'acctsessiontime'  => $secs,
                            'acctinputoctets'  => null,
                            'acctoutputoctets' => null,
                            'callingstationid' => $a['caller-id'] ?? null,
                            'source'           => 'router',
                        ];
                        $sess->push($row);
                    }
                } catch (\Throwable $e) {
                    // lewati
                }
            }
        }

        $sess = $sess->unique(function($x){
            return ($x->username ?? '').'|'.($x->framedipaddress ?? '');
        });

        return view('radius.sessions', [
            'devices'=>$devices, 'sel'=>$sel, 'q'=>$q, 'sess'=>$sess
        ]);
    }

    private function uptimeToSeconds(string $uptime): int
    {
        $pattern = '/(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/i';
        if (preg_match($pattern, $uptime, $m)) {
            $d = (int)($m[1] ?? 0);
            $h = (int)($m[2] ?? 0);
            $i = (int)($m[3] ?? 0);
            $s = (int)($m[4] ?? 0);
            return $d*86400 + $h*3600 + $i*60 + $s;
        }
        return 0;
    }

    /* ====================== Helpers: Router Sync ====================== */

    /**
     * Resolve Mikrotik target untuk username tertentu:
     * - mikrotik_id eksplisit dari form
     * - subscriptions.mikrotik_id (kalau ada)
     * - radacct NAS terakhir yang match dengan mikrotiks.host
     * - jika user hanya punya 1 device → pakai itu
     */
    private function resolveMikrotikForUser(?int $mikId, string $username): ?Mikrotik
    {
        $qUser = auth()->user();

        if ($mikId && $mikId > 0) {
            $m = Mikrotik::forUser($qUser)->where('id',$mikId)->first();
            if ($m) return $m;
        }

        if (Schema::hasTable('subscriptions')) {
            $sid = DB::table('subscriptions')->where('username',$username)->value('mikrotik_id');
            if ($sid) {
                $m = Mikrotik::forUser($qUser)->where('id',$sid)->first();
                if ($m) return $m;
            }
        }

        try {
            $nas = DB::connection('radius')->table('radacct')
                ->where('username',$username)
                ->orderByDesc('acctstarttime')
                ->value('nasipaddress');
            if ($nas) {
                $m = Mikrotik::forUser($qUser)->where('host',$nas)->first();
                if ($m) return $m;
            }
        } catch (\Throwable $e) {
            // abaikan
        }

        $only = Mikrotik::forUser($qUser)->where('radius_enabled',1)->get(['id','name','host']);
        if ($only->count() === 1) {
            return Mikrotik::find($only->first()->id);
        }

        return null;
    }

    /**
     * Upsert secret PPP di Mikrotik:
     * - Kalau belum ada: add (password wajib → jika null, buat random)
     * - Kalau ada: set hanya field yang disuplai (password/profile/disabled)
     * - Tidak pernah menuliskan password kosong
     */
    private function routerUpsert(Mikrotik $m, string $username, ?string $password, ?string $profile, ?string $statusAction): void
    {
        $svc = new RouterOSService($m);

        // cek apakah secret ada
        $exists = false;
        $secrets = $svc->pppSecrets();
        foreach ($secrets as $s) {
            if (($s['name'] ?? '') === $username) { $exists = true; break; }
        }

        if (!$exists) {
            $pwd = $password;
            if ($pwd === null || $pwd === '') $pwd = bin2hex(random_bytes(4));
            $prof = $profile ?: 'default';
            $svc->pppAdd($username, $pwd, $prof);
            if ($statusAction === 'deactivate') {
                $svc->pppSet($username, ['disabled'=>'yes']);
            }
            return;
        }

        $attrs = [];
        if ($password !== null && $password !== '') $attrs['password'] = $password;
        if ($profile  !== null && $profile  !== '') $attrs['profile']  = $profile;
        if ($statusAction === 'deactivate') $attrs['disabled'] = 'yes';
        if ($statusAction === 'activate')   $attrs['disabled'] = 'no';

        if (!empty($attrs)) $svc->pppSet($username, $attrs);
    }
}
