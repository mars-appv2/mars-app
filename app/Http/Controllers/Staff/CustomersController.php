<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class CustomersController extends Controller
{
    /* ================== Helpers skema dinamis ================== */
    protected function customerTable(): ?string
    {
        return Schema::hasTable('customers') ? 'customers' : null;
    }
    protected function routerTable(): ?string
    {
        if (Schema::hasTable('mikrotiks')) return 'mikrotiks';
        if (Schema::hasTable('mikrotik'))  return 'mikrotik';
        return null;
    }
    protected function cols(string $table): array
    {
        return Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
    }
    protected function firstExisting(array $candidates, array $cols): ?string
    {
        foreach ($candidates as $c) if (in_array($c,$cols,true)) return $c;
        return null;
    }
    protected function hasAnyRole(array $roles): bool
    {
        $u = Auth::user();
        if (!$u) return false;
        if (method_exists($u,'hasAnyRole')) return $u->hasAnyRole($roles);
        if (isset($u->role)) return in_array(strtolower($u->role), array_map('strtolower',$roles), true);
        if (method_exists($u,'roles')) return $u->roles()->whereIn('name',$roles)->exists();
        return false;
    }

    /* ================== Koneksi DB FreeRADIUS ================== */
    protected function radConnName(): string
    {
        // Jembatan variabel ENV yang kamu pakai
        return env('DB_RADIUS_CONNECTION',
               env('RADIUS_DB_CONNECTION',
               env('DB_FREERADIUS_CONNECTION', 'radius')));
    }
    protected function radDbName(): ?string
    {
        $v = env('DB_RADIUS_DATABASE', env('RADIUS_DB_DATABASE'));
        return $v && trim($v) !== '' ? $v : null;
    }
    protected function radTable(string $table)
    {
        $conn = $this->radConnName();
        $db   = $this->radDbName();
        $tbl  = $db ? ($db.'.'.$table) : $table;
        return DB::connection($conn)->table($tbl);
    }
    protected function radHas(string $table): bool
    {
        try {
            $conn = $this->radConnName();
            $db   = $this->radDbName() ?: DB::connection($conn)->getDatabaseName();
            $sql  = "select count(*) as c from information_schema.tables where table_schema = ? and table_name = ?";
            $row  = DB::connection($conn)->selectOne($sql, [$db, $table]);
            return (int)($row->c ?? 0) > 0;
        } catch (\Throwable $e) {
            \Log::warning("RADIUS hasTable check fail: ".$e->getMessage());
            return false;
        }
    }

    /** router yang boleh diakses user */
    protected function allowedRoutersForUser(int $userId)
    {
        $rt = $this->routerTable();
        if (!$rt) return collect();

        if ($this->hasAnyRole(['admin','operator'])) {
            return DB::table($rt)->orderBy('id','desc')->get();
        }

        $own = collect();
        $hasCreatedBy = Schema::hasColumn($rt,'created_by');
        $hasUserId    = Schema::hasColumn($rt,'user_id');
        if ($hasCreatedBy || $hasUserId) {
            $q = DB::table($rt)->orderBy('id','desc');
            if     ($hasCreatedBy && $hasUserId) $q->where(fn($w)=>$w->where('created_by',$userId)->orWhere('user_id',$userId));
            elseif ($hasCreatedBy)               $q->where('created_by',$userId);
            else                                  $q->where('user_id',$userId);
            $own = $q->get();
        }

        $assigned = collect();
        if (Schema::hasTable('staff_mikrotik')) {
            $assigned = DB::table($rt)
                ->join('staff_mikrotik',$rt.'.id','=','staff_mikrotik.mikrotik_id')
                ->where('staff_mikrotik.user_id',$userId)
                ->select($rt.'.*')
                ->orderBy($rt.'.id','desc')
                ->get();
        }

        return $own->concat($assigned)->unique('id')->values();
    }

    /* ========================= INDEX ========================= */
    public function index(Request $r)
    {
        $table     = $this->customerTable();
        $routers   = $this->allowedRoutersForUser((int)Auth::id());
        $q         = trim((string)$r->input('q',''));
        $routerVal = $r->input('router');

        $customers = collect();
        if ($table) {
            $cols      = $this->cols($table);
            $colRouter = $this->firstExisting(['mikrotik_id','router_id','device_id','mikrotikid'],$cols);

            $qq = DB::table($table);

            if ($q !== '') {
                $colName = $this->firstExisting(['name','customer_name','fullname','full_name','nama'],$cols);
                $colUser = $this->firstExisting(['username','user_name','login','rad_username','customer_username'],$cols);
                $colEmail= in_array('email',$cols,true) ? 'email' : null;
                $colPhone= in_array('phone',$cols,true) ? 'phone' : null;
                $colAddr = in_array('address',$cols,true) ? 'address' : null;

                $qq->where(function($w) use ($q,$colName,$colUser,$colEmail,$colPhone,$colAddr){
                    foreach ([$colName,$colUser,$colEmail,$colPhone,$colAddr] as $c) if ($c) $w->orWhere($c,'like',"%{$q}%");
                });
            }

            if ($colRouter) {
                $allowedIds = $routers->pluck('id')->map(fn($v)=>(int)$v)->all();
                if ($routerVal) {
                    if (in_array((int)$routerVal,$allowedIds,true)) $qq->where($colRouter,(int)$routerVal);
                    else $qq->whereRaw('1=0');
                } else {
                    if (!empty($allowedIds)) $qq->whereIn($colRouter,$allowedIds);
                    else $qq->whereRaw('1=0');
                }
            }

            if (in_array('created_at',$cols,true)) $qq->orderByDesc('created_at');
            elseif (in_array('id',$cols,true))     $qq->orderByDesc('id');

            $customers = $qq->paginate(20);
        }

        return view('staff.customers.index', [
            'customers'=>$customers,
            'routers'=>$routers,
            'q'=>$q,
            'routerFilter'=>$routerVal
        ]);
    }

    public function create()
    {
        $routers = $this->allowedRoutersForUser((int)Auth::id());

        // Plans aman tanpa asumsi kolom
        $plans = collect();
        if (Schema::hasTable('plans')) {
            $pcols   = Schema::getColumnListing('plans');
            $idCol   = in_array('id',$pcols,true)   ? 'id'   : null;
            $nameCol = in_array('name',$pcols,true) ? 'name' : (in_array('plan',$pcols,true) ? 'plan' : null);
            if ($idCol && $nameCol) {
                $rows = DB::table('plans')->select($idCol.' as id',$nameCol.' as name')->orderBy($nameCol)->get();
                $plans = $rows->map(fn($r)=>(object)['id'=>$r->id,'name'=>$r->name]);
            } elseif ($idCol) {
                $rows = DB::table('plans')->select($idCol.' as id')->orderBy($idCol,'desc')->get();
                $plans = $rows->map(fn($r)=>(object)['id'=>$r->id,'name'=>'Plan #'.$r->id]);
            }
        }

        return view('staff.customers.create', compact('routers','plans'));
    }

    /* ====================== RADIUS helpers ====================== */
    protected function planRateLimit(?int $planId): ?string
    {
        if (!$planId || !Schema::hasTable('plans')) return null;

        $cols = Schema::getColumnListing('plans');
        $plan = DB::table('plans')->where('id',$planId)->first();
        if (!$plan) return null;

        foreach (['rate_limit','rate-limit','rate','bandwidth'] as $c) {
            if (in_array($c,$cols,true) && !empty($plan->{$c})) return (string)$plan->{$c};
        }
        if (in_array('comment',$cols,true) && !empty($plan->comment)) {
            if (preg_match('/rate[-_]?limit\s*=\s*([^\s;]+)/i', (string)$plan->comment, $m)) return $m[1];
        }
        return null;
    }

    protected function provisionToRadius(string $username, string $password, string $serviceType = 'pppoe', ?int $planId = null): void
    {
        $conn = $this->radConnName();
        $dbnm = $this->radDbName() ?: DB::connection($conn)->getDatabaseName();
        \Log::info("[RADIUS] using connection={$conn} db={$dbnm} for user={$username}");

        $ok = [];

        try {
            if ($this->radHas('radcheck')) {
                $this->radTable('radcheck')->updateOrInsert(
                    ['username'=>$username,'attribute'=>'Cleartext-Password'],
                    ['op'=>':=','value'=>$password]
                );
                $ok[] = 'radcheck';
            } else {
                \Log::warning("[RADIUS] table radcheck not found on {$conn} ({$dbnm})");
            }
        } catch (\Throwable $e) {
            \Log::error("[RADIUS] radcheck upsert fail: ".$e->getMessage());
        }

        try {
            if ($this->radHas('radusergroup')) {
                $group = ($serviceType === 'hotspot') ? 'hotspot' : 'pppoe';
                $this->radTable('radusergroup')->where('username',$username)->delete();
                $this->radTable('radusergroup')->insert([
                    'username'=>$username,'groupname'=>$group,'priority'=>1
                ]);
                $ok[]='radusergroup';
            } else {
                \Log::warning("[RADIUS] table radusergroup not found on {$conn} ({$dbnm})");
            }
        } catch (\Throwable $e) {
            \Log::error("[RADIUS] radusergroup write fail: ".$e->getMessage());
        }

        try {
            if ($this->radHas('radreply')) {
                if ($planId) {
                    $rate = $this->planRateLimit($planId);
                    if ($rate) {
                        $this->radTable('radreply')->updateOrInsert(
                            ['username'=>$username,'attribute'=>'Mikrotik-Rate-Limit'],
                            ['op'=>':=','value'=>$rate]
                        );
                        $ok[]='radreply';
                    } else {
                        $this->radTable('radreply')
                            ->where('username',$username)
                            ->where('attribute','Mikrotik-Rate-Limit')
                            ->delete();
                    }
                }
            } else {
                \Log::warning("[RADIUS] table radreply not found on {$conn} ({$dbnm})");
            }
        } catch (\Throwable $e) {
            \Log::error("[RADIUS] radreply upsert fail: ".$e->getMessage());
        }

        if (empty($ok)) {
            \Log::error("[RADIUS] NO TABLE WRITTEN. Check DB_RADIUS_CONNECTION/DB_RADIUS_DATABASE and privileges.");
        } else {
            \Log::info("[RADIUS] written tables: ".implode(',', $ok));
        }
    }

    /* ========================= STORE (STAFF) ========================= */
    public function store(Request $r)
    {
        $table = $this->customerTable();
        if (!$table) return back()->with('err','Tabel customers tidak ditemukan.');

        $cols      = $this->cols($table);
        $colName   = $this->firstExisting(['name','customer_name','fullname','full_name','nama'],$cols);
        $colUser   = $this->firstExisting(['username','user_name','login','rad_username','customer_username'],$cols);
        $colRouter = $this->firstExisting(['mikrotik_id','router_id','device_id','mikrotikid'],$cols);

        $r->validate([
            'name'          => 'required|string|max:120',
            'username'      => 'required|string|max:120' . ($colUser ? '|unique:'.$table.','.$colUser : ''),
            'password'      => 'required|string|min:6|max:64',
            'email'         => 'nullable|email|max:190',
            'phone'         => 'nullable|string|max:32',
            'address'       => 'nullable|string|max:255',
            'mikrotik_id'   => 'required|integer',
            'service_type'  => 'required|string|in:pppoe,hotspot,other',
            'router_profile'=> 'nullable|string|max:64',
            'vlan_id'       => 'nullable|integer|min:1|max:4094',
            'ip_address'    => 'nullable|string|max:64',
            'plan_id'       => 'nullable|integer',
            'provision_to'  => 'nullable|string|in:radius,mikrotik,none',
            'note'          => 'nullable|string|max:255',
        ]);

        $allowedIds = $this->allowedRoutersForUser((int)Auth::id())->pluck('id')->map(fn($v)=>(int)$v)->all();
        if (!in_array((int)$r->mikrotik_id, $allowedIds, true)) {
            return back()->with('err','Router tidak valid / bukan milik akun ini.')->withInput();
        }

        $payload = [];
        if ($colName)   $payload[$colName]   = $r->name;
        if ($colUser)   $payload[$colUser]   = $r->username;
        if ($colRouter) $payload[$colRouter] = (int)$r->mikrotik_id;
        foreach (['email','phone','address','plan_id','service_type','router_profile','vlan_id','ip_address','note'] as $c) {
            if (in_array($c,$cols,true) && $r->filled($c)) $payload[$c] = $r->input($c);
        }
        if (in_array('is_active',$cols,true))        $payload['is_active']  = false; // pending
        if (in_array('created_by',$cols,true))       $payload['created_by'] = Auth::id();
        if (in_array('password_plain',$cols,true))   $payload['password_plain'] = $r->password;
        if (in_array('provision_status',$cols,true)) $payload['provision_status'] = 'pending';
        if (in_array('created_at',$cols,true))       $payload['created_at'] = now();
        if (in_array('updated_at',$cols,true))       $payload['updated_at'] = now();

        $id = DB::table($table)->insertGetId($payload);

        if (in_array('provision_status',$cols,true)) {
            return redirect()->route('staff.customers.index')->with('ok','Pelanggan dibuat. Menunggu teknisi untuk ACCEPT.');
        }

        // fallback lama: langsung provision bila kolom pending belum ada
        $this->provisionToRadius(
            $r->username,
            $r->password,
            $r->service_type,
            $r->input('plan_id') ? (int)$r->input('plan_id') : null
        );
        if (in_array('is_active',$cols,true)) {
            DB::table($table)->where('id',$id)->update(['is_active'=>true,'updated_at'=>now()]);
        }
        try {
            if (class_exists(\App\Services\Provisioner::class)) {
                $customer = DB::table($table)->where('id',$id)->first();
                app(\App\Services\Provisioner::class)->activate($customer);
            }
        } catch (\Throwable $e) { \Log::warning('Provisioner fallback: '.$e->getMessage()); }

        return redirect()->route('staff.customers.index')->with('ok','Pelanggan dibuat & langsung diprovision (fallback).');
    }

    /* ========================= ACCEPT (TEKNISI) ========================= */
    public function accept(Request $r, int $id)
    {
        abort_unless($this->hasAnyRole(['teknisi','operator','admin']), 403, 'Peran tidak sesuai.');

        $table = $this->customerTable();
        abort_unless($table, 404);

        $cols      = $this->cols($table);
        $colUser   = $this->firstExisting(['username','user_name','login','rad_username','customer_username'],$cols);
        $colRouter = $this->firstExisting(['mikrotik_id','router_id','device_id','mikrotikid'],$cols);

        $c = DB::table($table)->where('id',$id)->first();
        abort_unless($c, 404);

        $allowedIds = $this->allowedRoutersForUser((int)Auth::id())->pluck('id')->map(fn($v)=>(int)$v)->all();
        if ($colRouter && !in_array((int)($c->{$colRouter} ?? 0), $allowedIds, true)) {
            abort(403,'Router tidak diperbolehkan.');
        }

        if (in_array('provision_status',$cols,true) && ($c->provision_status ?? 'pending') !== 'pending') {
            return back()->with('ok','Data sudah di-accept.');
        }

        $username    = $colUser ? ($c->{$colUser} ?? null) : null;
        $password    = property_exists($c,'password_plain') ? ($c->password_plain ?? null) : null;
        $serviceType = property_exists($c,'service_type') ? ($c->service_type ?? 'pppoe') : 'pppoe';
        $planId      = property_exists($c,'plan_id') ? ($c->plan_id ?? null) : null;
        $routerId    = $colRouter ? (int)($c->{$colRouter} ?? 0) : 0;

        if (!$username || !$password) {
            return back()->with('err','Username / Password tidak lengkap, tidak bisa provisioning.');
        }

        // 1) Tulis ke RADIUS
        $this->provisionToRadius((string)$username,(string)$password,(string)$serviceType, $planId ? (int)$planId : null);

        // 2) Update status di customers
        $upd = [];
        if (in_array('provision_status',$cols,true)) $upd['provision_status'] = 'accepted';
        if (in_array('is_active',$cols,true))        $upd['is_active'] = true;
        if (in_array('accepted_by',$cols,true))      $upd['accepted_by'] = Auth::id();
        if (in_array('accepted_at',$cols,true))      $upd['accepted_at'] = now();
        if (in_array('updated_at',$cols,true))       $upd['updated_at'] = now();
        if (!empty($upd)) DB::table($table)->where('id',$id)->update($upd);

        // 3) (OPSIONAL) Dorong langsung ke MikroTik bila diaktifkan
        if (filter_var(env('STAFF_ACCEPT_PUSH_TO_ROUTER', false), FILTER_VALIDATE_BOOL)) {
            try {
                $rtTable = $this->routerTable();
                if ($routerId && $rtTable) {
                    $mk = DB::table($rtTable)->where('id',$routerId)->first();
                    if ($mk) {
                        // Pakai RouterOS PHP Client (ada di project kamu)
                        $client = new \RouterOS\Client([
                            'host'     => $mk->host ?? '127.0.0.1',
                            'user'     => $mk->username ?? $mk->user ?? 'admin',
                            'pass'     => $mk->password ?? $mk->pass ?? '',
                            'port'     => (int)($mk->port ?? 8728),
                            'timeout'  => 8,
                            'attempts' => 1,
                        ]);

                        if ($serviceType === 'pppoe') {
                            // upsert PPP secret
                            $print = (new \RouterOS\Query('/ppp/secret/print'))
                                ->where('name', $username);
                            $found = $client->query($print)->read();

                            if (!empty($found) && isset($found[0]['.id'])) {
                                $set = (new \RouterOS\Query('/ppp/secret/set'))
                                    ->equal('.id', $found[0]['.id'])
                                    ->equal('password', $password);
                                if (!empty($c->router_profile ?? null)) $set->equal('profile', $c->router_profile);
                                $client->query($set)->read();
                            } else {
                                $add = (new \RouterOS\Query('/ppp/secret/add'))
                                    ->equal('name',$username)
                                    ->equal('password',$password)
                                    ->equal('service','pppoe');
                                if (!empty($c->router_profile ?? null)) $add->equal('profile', $c->router_profile);
                                $add->equal('comment','staff-accept#'.Auth::id());
                                $client->query($add)->read();
                            }
                        } elseif ($serviceType === 'hotspot') {
                            // upsert Hotspot user
                            $print = (new \RouterOS\Query('/ip/hotspot/user/print'))
                                ->where('name', $username);
                            $found = $client->query($print)->read();

                            if (!empty($found) && isset($found[0]['.id'])) {
                                $set = (new \RouterOS\Query('/ip/hotspot/user/set'))
                                    ->equal('.id', $found[0]['.id'])
                                    ->equal('password', $password);
                                if (!empty($c->router_profile ?? null)) $set->equal('profile', $c->router_profile);
                                $client->query($set)->read();
                            } else {
                                $add = (new \RouterOS\Query('/ip/hotspot/user/add'))
                                    ->equal('name',$username)
                                    ->equal('password',$password);
                                if (!empty($c->router_profile ?? null)) $add->equal('profile', $c->router_profile);
                                $add->equal('comment','staff-accept#'.Auth::id());
                                $client->query($add)->read();
                            }
                        }
                        \Log::info("Router push OK for user={$username} on router={$routerId}");
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Router push FAILED: '.$e->getMessage());
            }
        }

        // 4) Notifikasi aplikasi utama via job table bila tersedia
        try {
            if (Schema::hasTable('provision_jobs')) {
                DB::table('provision_jobs')->insert([
                    'type'      => 'activate_customer',
                    'payload'   => json_encode([
                        'customer_id'=>$id,
                        'username'   =>$username,
                        'router_id'  =>$routerId,
                        'service'    =>$serviceType
                    ]),
                    'status'    => 'queued',
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]);
            }
            if (class_exists(\App\Services\Provisioner::class)) {
                $customer = DB::table($table)->where('id',$id)->first();
                app(\App\Services\Provisioner::class)->activate($customer);
            }
        } catch (\Throwable $e) {
            \Log::warning('Provision notify failed: '.$e->getMessage());
        }

        return back()->with('ok','Customer di-ACCEPT. RADIUS OK'.(env('STAFF_ACCEPT_PUSH_TO_ROUTER') ? ' & router dipush.' : '.'));
    }
}
