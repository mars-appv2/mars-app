<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // --- Kumpulkan PPP profiles dari Mikrotik (target = device terpilih / semua) ---
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
                // diamkan; akan difallback ke DB
            }
        }

        // --- Fallback/augment dari tabel lokal plans (jika ada) ---
        $plansFromDb = DB::table('plans')
            ->when($sel > 0, fn($q) => $q->where('mikrotik_id', $sel))
            ->pluck('name')
            ->all();

        $plans = array_values(array_unique(array_merge(array_keys($plansFromRos), $plansFromDb)));
        sort($plans);

        // --- Users dari DB radius (maks 10k) ---
        $q   = trim((string) $r->query('q',''));
        $rad = DB::connection('radius');

        $usersQ = $rad->table('radcheck')
            ->select('username')
            ->where('attribute','Cleartext-Password');

        if ($q !== '') $usersQ->where('username','like',"%{$q}%");

        $users = $usersQ->orderBy('username')->limit(10000)->get();

        // Map: username -> group (plan)
        $groupMap = $rad->table('radusergroup')
            ->whereIn('username', $users->pluck('username')->all() ?: ['__none__'])
            ->pluck('groupname','username');

        // Map: username -> status aktif/inaktif (Auth-Type := Reject)
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

    /** Tambah / upsert user */
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

        return back()->with('ok','User disimpan');
    }

    /** Update password (alias & kanonik) */
    public function usersUpdatePassword(Request $r)
    {
        $r->validate([
            'username'=>'required|string|max:255',
            'password'=>'required|string|max:255',
        ]);

        DB::connection('radius')->table('radcheck')
            ->where('username',$r->username)
            ->where('attribute','Cleartext-Password')
            ->update(['value'=>$r->password]);

        return back()->with('ok','Password diperbarui');
    }
    public function usersPassword(Request $r) { return $this->usersUpdatePassword($r); } // alias
    public function usersPass(Request $r)      { return $this->usersUpdatePassword($r); } // alias legacy

    /** Update plan (alias & kanonik) */
    public function usersUpdatePlan(Request $r)
    {
        $r->validate([
            'username'=>'required|string|max:255',
            'plan'=>'nullable|string|max:255',
        ]);

        $rad = DB::connection('radius');
        if ($r->filled('plan')) {
            $rad->table('radusergroup')->updateOrInsert(
                ['username'=>$r->username],
                ['groupname'=>$r->plan,'priority'=>1]
            );
        } else {
            $rad->table('radusergroup')->where('username',$r->username)->delete();
        }
        return back()->with('ok','Plan diperbarui');
    }
    public function usersPlan(Request $r) { return $this->usersUpdatePlan($r); } // alias

    /** Toggle aktif / nonaktif (Auth-Type := Reject) + alias */
    public function usersUpdateStatus(Request $r)
    {
        $r->validate([
            'username'=>'required|string|max:255',
            'action'=>'required|in:activate,deactivate',
        ]);

        $rad = DB::connection('radius');

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

        return back()->with('ok','Status diperbarui');
    }
    public function usersStatus(Request $r) { return $this->usersUpdateStatus($r); } // alias
    public function usersToggle(Request $r) { return $this->usersUpdateStatus($r); } // alias

    /** Hapus user (radcheck + radusergroup + radreply) */
    public function usersDelete(Request $r)
    {
        $r->validate(['username'=>'required|string|max:255']);

        $rad = DB::connection('radius');
        $rad->table('radcheck')->where('username',$r->username)->delete();
        $rad->table('radusergroup')->where('username',$r->username)->delete();
        if ($rad->getPdo()->query("SHOW TABLES LIKE 'radreply'")->rowCount()) {
            $rad->table('radreply')->where('username',$r->username)->delete();
        }

        return back()->with('ok','User dihapus');
    }

    /** Import users dari Mikrotik (stub/placeholder) */
    public function usersImport(Request $r)
    {
        // Implementasi kamu sudah ada — pertahankan.
        return back()->with('ok','Import diproses');
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

        // Fallback/augment dari Mikrotik /ppp/active
        if ($sel > 0) {
            $mk = $devices->firstWhere('id',$sel);
            if ($mk) {
                try {
                    $svc = new RouterOSService($mk);
                    $active = $svc->pppActive(); // name, address, uptime, caller-id, etc.

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

        // Uniq by username+framedipaddress agar tidak dobel jika ada di keduanya
        $sess = $sess->unique(function($x){
            return ($x->username ?? '').'|'.($x->framedipaddress ?? '');
        });

        return view('radius.sessions', [
            'devices'=>$devices, 'sel'=>$sel, 'q'=>$q, 'sess'=>$sess
        ]);
    }

    private function uptimeToSeconds(string $uptime): int
    {
        // format ROS: "1d2h3m4s", "3h20m", dst
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
}
