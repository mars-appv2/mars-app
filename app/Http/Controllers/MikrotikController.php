<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\MonitorTarget;
use App\Services\RouterOSService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use RouterOS\Client;
use RouterOS\Query;

// === tambahan sinkronisasi RADIUS/Billing ===
use App\Services\RadiusBillingSync as Sync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MikrotikController extends Controller
{
    /* =====================  LIST  ===================== */
    public function index()
    {
        $list = Mikrotik::forUser(auth()->user())
            ->orderBy('id', 'desc')
            ->get();

        return view('mikrotik.index', compact('list'));
    }

    /* =====================  CRUD MIKROTIK  ===================== */

    private function newSecret(): string
    {
        $len = (int) env('RADIUS_DEFAULT_SECRET_LENGTH', 24);
        return Str::random(max(16, $len));
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name'            => 'required|string|max:255',
            'host'            => 'required|string|max:255',
            'port'            => 'nullable|integer',
            'username'        => 'required|string|max:255',
            'password'        => 'required|string|max:255',
            'radius_enabled'  => 'nullable',
        ]);

        $d['port']      = $d['port'] ?: 8728;
        $d['owner_id']  = auth()->id();                   // pemilik perangkat
        $enabled        = $r->has('radius_enabled');
        $d['radius_enabled'] = $enabled ? 1 : 0;

        if ($enabled && empty($d['radius_secret'] ?? null)) {
            $d['radius_secret'] = $this->newSecret();
        }

        $mikrotik = Mikrotik::create($d);

        // opsional: langsung provision bila dicentang
        if ($enabled) {
            try {
                $this->provisionRadiusAndRouter($mikrotik);
                return back()->with('ok', 'Saved & RADIUS provisioned');
            } catch (\Throwable $e) {
                return back()->with('ok', 'Saved (provision failed: '.$e->getMessage().')');
            }
        }

        return back()->with('ok', 'Saved');
    }

    public function edit(Mikrotik $mikrotik)
    {
        $this->authorize('view', $mikrotik);
        return view('mikrotik.edit', compact('mikrotik'));
    }

    public function update(Request $r, Mikrotik $mikrotik)
    {
        $this->authorize('update', $mikrotik);

        $d = $r->validate([
            'name'            => 'required|string|max:255',
            'host'            => 'required|string|max:255',
            'port'            => 'nullable|integer',
            'username'        => 'required|string|max:255',
            'password'        => 'required|string|max:255',
            'radius_enabled'  => 'nullable',
        ]);

        $d['port'] = $d['port'] ?: 8728;

        $wasEnabled = (bool) $mikrotik->radius_enabled;
        $nowEnabled = $r->has('radius_enabled');
        $d['radius_enabled'] = $nowEnabled ? 1 : 0;

        if ($nowEnabled && empty($mikrotik->radius_secret)) {
            $d['radius_secret'] = $this->newSecret();
        }

        // deteksi perubahan koneksi sebelum save
        $mikrotik->fill($d);
        $changedConn = $mikrotik->isDirty(['host','port','username','password']);
        $mikrotik->save();

        // re-provision kalau baru ON atau koneksi berubah saat ON
        $shouldProvision = $nowEnabled && (!$wasEnabled || $changedConn);

        if ($shouldProvision) {
            try {
                $this->provisionRadiusAndRouter($mikrotik);
                return redirect()->route('mikrotik.index')
                    ->with('success', 'Perangkat berhasil diupdate & RADIUS diprovision');
            } catch (\Throwable $e) {
                return redirect()->route('mikrotik.index')
                    ->with('success', 'Perangkat berhasil diupdate (provision gagal: '.$e->getMessage().')');
            }
        }

        return redirect()->route('mikrotik.index')->with('success', 'Perangkat berhasil diupdate');
    }

    public function delete(Mikrotik $mikrotik)
    {
        $this->authorize('delete', $mikrotik);
        $mikrotik->delete();
        return back()->with('ok', 'Deleted');
    }

    public function destroy(Mikrotik $mikrotik)
    {
        $this->authorize('delete', $mikrotik);
        $mikrotik->delete();
        return redirect()->route('mikrotik.index')->with('success', 'Perangkat berhasil dihapus');
    }

    /* =====================  DASHBOARD & UTIL  ===================== */

    public function dashboard(Mikrotik $mikrotik)
    {
        $this->authorize('view', $mikrotik);
        try {
            $ros = new RouterOSService($mikrotik);
            $if  = $ros->interfaces();
            $err = null;
        } catch (\Throwable $e) {
            $if = [];
            $err = $e->getMessage();
        }
        return view('mikrotik.dashboard', compact('mikrotik','if','err'));
    }

    public function monitor(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('view', $mikrotik);
        $r->validate(['iface'=>'required']);
        $ros = new RouterOSService($mikrotik);
        return response()->json($ros->monitorInterface($r->iface));
    }

    public function vlanCreate(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d = $r->validate(['name'=>'required','interface'=>'required','vid'=>'required|integer|min:1|max:4094']);
        try{
            (new RouterOSService($mikrotik))->addVlan($d['name'],$d['interface'],$d['vid']);
            return back()->with('ok','VLAN created');
        }catch(\Throwable $e){
            return back()->with('err','Gagal buat VLAN: '.$e->getMessage());
        }
    }

    public function bridgeCreate(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d=$r->validate(['bridge'=>'required','iface'=>'nullable']);
        try{
            $svc=new RouterOSService($mikrotik);
            $svc->addBridge($d['bridge']);
            if(!empty($d['iface'])) $svc->addBridgePort($d['bridge'],$d['iface']);
            return back()->with('ok','Bridge/Port created');
        }catch(\Throwable $e){
            return back()->with('err','Gagal buat Bridge/Port: '.$e->getMessage());
        }
    }

    /* ===== PPPoE (authorize ditambah) ===== */

    public function pppIndex(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('view', $mikrotik);
        $svc      = new RouterOSService($mikrotik);
        $secrets  = $svc->pppSecrets();
        $active   = $svc->pppActive();
        $profiles = $svc->pppProfiles();

        $recs = MonitorTarget::where(['mikrotik_id'=>$mikrotik->id,'target_type'=>'pppoe'])->get();
        $recMap = [];
        foreach ($recs as $mt) $recMap[$mt->target_key] = (bool)$mt->enabled;

        $q = trim((string)$r->query('q',''));
        if ($q !== '') {
            $qq = mb_strtolower($q);
            $secrets = array_values(array_filter($secrets, function($s) use($qq){
                $name = mb_strtolower($s['name'] ?? '');
                $comment = mb_strtolower($s['comment'] ?? '');
                $addr = mb_strtolower($s['remote-address'] ?? $s['address'] ?? '');
                return strpos($name,$qq)!==false || strpos($comment,$qq)!==false || strpos($addr,$qq)!==false;
            }));
            $active = array_values(array_filter($active, function($a) use($qq){
                $name = mb_strtolower($a['name'] ?? '');
                $addr = mb_strtolower($a['address'] ?? '');
                return strpos($name,$qq)!==false || strpos($addr,$qq)!==false;
            }));
        }

        return view('mikrotik.pppoe', compact('mikrotik','secrets','active','profiles','q','recMap'));
    }

    public function pppAdd(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $mode = $r->input('mode','client');
        try{
            $svc = new RouterOSService($mikrotik);

            if ($mode === 'profile') {
                // Tambah PROFILE baru → sinkronkan ke Plans
                $d=$r->validate(['pname'=>'required','rate'=>'required','parent'=>'nullable']);
                $svc->pppProfileAdd($d['pname'],$d['rate'],$d['parent']??null);

                // === sync ke plans (DB) ===
                Sync::syncProfileToPlan($d['pname'], (int)$mikrotik->id);

                return back()->with('ok','Profil PPPoE ditambahkan');
            } else {
                // Tambah CLIENT (secret)
                $d=$r->validate(['name'=>'required','password'=>'required','profile'=>'nullable','record'=>'nullable']);
                $profile = $d['profile'] ?: 'default';
                $svc->pppAdd($d['name'],$d['password'],$profile);

                if($r->has('record')){
                    MonitorTarget::firstOrCreate(
                        ['mikrotik_id'=>$mikrotik->id,'target_type'=>'pppoe','target_key'=>$d['name']],
                        ['label'=>$d['name'],'enabled'=>true]
                    );
                }

                // === sync ke RADIUS + subscriptions ===
                Sync::syncUserToRadiusAndBilling($d['name'], $d['password'], $profile, (int)$mikrotik->id, false);

                return back()->with('ok','PPPoE added');
            }
        }catch(\Throwable $e){
            return back()->with('err','Gagal simpan: '.$e->getMessage())->withInput();
        }
    }

    public function pppEdit(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d=$r->validate(['name'=>'required','password'=>'nullable','profile'=>'nullable','action'=>'nullable','record'=>'nullable']);
        try{
            $svc = new RouterOSService($mikrotik);
            $attrs = [];
            $pwd   = null;
            $prof  = null;

            if(strlen((string)($d['password']??''))) { $attrs['password'] = $d['password']; $pwd  = $d['password']; }
            if(strlen((string)($d['profile']??'')))  { $attrs['profile']  = $d['profile'];  $prof = $d['profile']; }
            $act = $r->input('action');
            $disabled = null;
            if($act === 'disable'){ $attrs['disabled'] = 'yes'; $disabled = true; }
            else if($act === 'enable'){ $attrs['disabled'] = 'no'; $disabled = false; }

            if (!empty($attrs)) $svc->pppSet($d['name'], $attrs);

            $mt=MonitorTarget::where(['mikrotik_id'=>$mikrotik->id,'target_type'=>'pppoe','target_key'=>$d['name']])->first();
            if($r->has('record')){
                if(!$mt) MonitorTarget::create(['mikrotik_id'=>$mikrotik->id,'target_type'=>'pppoe','target_key'=>$d['name'],'label'=>$d['name'],'enabled'=>true]);
                else $mt->update(['enabled'=>true]);
            } else {
                if($mt) $mt->update(['enabled'=>false]);
            }

            // === sinkronkan ke RADIUS + billing ===
            // password/profile hanya diset bila ada input (null = tidak mengubah)
            if ($prof) {
                Sync::syncProfileToPlan($prof, (int)$mikrotik->id);
            }
            Sync::syncUserToRadiusAndBilling($d['name'], $pwd, $prof, (int)$mikrotik->id, false);

            // === dorong ke router juga (set & disconnect agar profile baru aktif) ===
            Sync::pushToRouter((int)$mikrotik->id, $d['name'], $pwd, $prof, $disabled, true);

            return back()->with('ok','PPPoE updated');
        }catch(\Throwable $e){
            return back()->with('err','Update PPPoE gagal: '.$e->getMessage());
        }
    }

    public function pppoeDelete(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('delete', $mikrotik);
        $r->validate(['name'=>'required']);
        try{
            (new RouterOSService($mikrotik))->pppRemove($r->name);
            MonitorTarget::where(['mikrotik_id'=>$mikrotik->id,'target_type'=>'pppoe','target_key'=>$r->name])->delete();

            // === bersihkan RADIUS ===
            try {
                $rad = DB::connection('radius');
                $rad->table('radcheck')->where('username',$r->name)->delete();
                $rad->table('radusergroup')->where('username',$r->name)->delete();
                if ($rad->getPdo()->query("SHOW TABLES LIKE 'radreply'")->rowCount()) {
                    $rad->table('radreply')->where('username',$r->name)->delete();
                }
            } catch (\Throwable $e) { /* abaikan */ }

            // === tandai subscription non-aktif (jaga histori) ===
            if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions','status')) {
                $q = DB::table('subscriptions')->where('username',$r->name);
                if (Schema::hasColumn('subscriptions','mikrotik_id')) $q->where('mikrotik_id',(int)$mikrotik->id);
                $q->update(['status'=>'inactive','updated_at'=>now()]);
            }

            return back()->with('ok','Deleted');
        }catch(\Throwable $e){
            return back()->with('err','Hapus gagal: '.$e->getMessage());
        }
    }

    /* =====================  IP STATIC  ===================== */

    public function ipStatic(Mikrotik $mikrotik)
    {
        $this->authorize('view', $mikrotik);
        try {
            $svc = new RouterOSService($mikrotik);
            $interfaces = $svc->interfaces();
        } catch (\Throwable $e) {
            $interfaces = [];
        }
        return view('mikrotik.ipstatic', [
            'mikrotik'   => $mikrotik,
            'interfaces' => $interfaces,
            'if'         => $interfaces,
        ]);
    }

    public function ipStaticAdd(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d=$r->validate(['ip'=>'required','iface'=>'required','comment'=>'nullable']);
        try{
            $svc = new RouterOSService($mikrotik);
            if (method_exists($svc,'ipAddressAdd')) {
                $svc->ipAddressAdd($d['ip'],$d['iface'],$d['comment'] ?? '');
            } else {
                $svc->addToAddressList('static',$d['ip'],$d['comment'] ?? '');
            }
            return back()->with('ok','IP ditambahkan');
        }catch(\Throwable $e){
            return back()->with('err','Tambah IP gagal: '.$e->getMessage());
        }
    }

    public function ipStaticRemove(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d=$r->validate(['ip'=>'required']);
        try{
            $svc = new RouterOSService($mikrotik);
            if (method_exists($svc,'ipAddressRemove')) {
                $svc->ipAddressRemove($d['ip']);
            } else {
                $svc->removeFromAddressList('static',$d['ip']);
            }
            MonitorTarget::where(['mikrotik_id'=>$mikrotik->id,'target_type'=>'ip','target_key'=>$d['ip']])->delete();
            return back()->with('ok','IP dihapus');
        }catch(\Throwable $e){
            return back()->with('err','Hapus IP gagal: '.$e->getMessage());
        }
    }

    public function ipStaticRecord(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d=$r->validate(['ip'=>'required','enable'=>'nullable']);
        if($r->has('enable')){
            MonitorTarget::firstOrCreate(
                ['mikrotik_id'=>$mikrotik->id,'target_type'=>'ip','target_key'=>$d['ip']],
                ['label'=>$d['ip'],'enabled'=>true]
            );
        } else {
            MonitorTarget::where(['mikrotik_id'=>$mikrotik->id,'target_type'=>'ip','target_key'=>$d['ip']])
                ->update(['enabled'=>false]);
        }
        return back()->with('ok','Recording updated');
    }

    public function addInterfaceTarget(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d=$r->validate(['iface'=>'required','label'=>'nullable']);
        MonitorTarget::firstOrCreate(
            ['mikrotik_id'=>$mikrotik->id,'target_type'=>'interface','target_key'=>$d['iface']],
            ['label'=>$d['label']?:$d['iface'],'enabled'=>true]
        );
        return back()->with('ok','Monitor target added');
    }

    public function pppRecord(Mikrotik $mikrotik, Request $r)
    {
        $this->authorize('update', $mikrotik);
        $d = $r->validate(["name"=>"required","enable"=>"nullable","label"=>"nullable"]);
        $name = $d["name"]; $label = $d["label"] ?? $name; $on = $r->has("enable");
        \Log::info("[ROS_UI] ppp.record.req", ["id"=>$mikrotik->id, "name"=>$name, "enable"=>$on]);

        $mt = MonitorTarget::where([
            "mikrotik_id"=>$mikrotik->id, "target_type"=>"pppoe", "target_key"=>$name
        ])->first();

        if ($on) {
            if (!$mt) {
                MonitorTarget::create([
                    "mikrotik_id"=>$mikrotik->id, "target_type"=>"pppoe", "target_key"=>$name,
                    "label"=>$label, "enabled"=>true
                ]);
            } else {
                $mt->update(["enabled"=>true, "label"=>$label]);
            }
            return back()->with("ok","Recording enabled");
        } else {
            if ($mt) { $mt->update(["enabled"=>false]); }
            return back()->with("ok","Recording disabled");
        }
    }

    public function monitorQueue($id, Request $r)
    {
        $m = Mikrotik::forUser(auth()->user())->findOrFail($id);
        $this->authorize('view', $m);
        $qname = $r->input('queue');

        try{
            $c = new Client([
                'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                'port'=>$m->port ?: 8728,'timeout'=>5,'attempts'=>1
            ]);

            $q = (new Query('/queue/simple/print'))
                ->where('name',$qname)
                ->equal('.proplist','rate,bytes');
            $res = $c->query($q)->read();

            if (empty($res)) return response()->json(['rx'=>0,'tx'=>0],200);

            $row = $res[0];

            if (!empty($row['rate'])) {
                $rate = $row['rate'];
                $pos  = strpos($rate,'/');
                $rx   = (int)substr($rate,0,$pos);
                $tx   = (int)substr($rate,$pos+1);
                return response()->json(['rx'=>$rx,'tx'=>$tx],200);
            }

            $bytes = $row['bytes'] ?? '0/0';
            $pos   = strpos($bytes,'/');
            $brx   = (int)substr($bytes,0,$pos);
            $btx   = (int)substr($bytes,$pos+1);

            $cacheKey = "qbytes:{$id}:{$qname}";
            $prev = cache($cacheKey);
            cache([$cacheKey => ['brx'=>$brx,'btx'=>$btx,'t'=>microtime(true)]], 300);

            if ($prev && !empty($prev['t'])) {
                $dt  = max(0.5, microtime(true) - $prev['t']);
                $drx = max(0, $brx - $prev['brx']);
                $dtx = max(0, $btx - $prev['btx']);
                $rx  = (int)round($drx / $dt * 8);
                $tx  = (int)round($dtx / $dt * 8);
                return response()->json(['rx'=>$rx,'tx'=>$tx],200);
            }

            return response()->json(['rx'=>0,'tx'=>0],200);

        }catch(\Throwable $e){
            \Log::error('MONQ fail: '.$e->getMessage());
            return response()->json(['rx'=>0,'tx'=>0,'err'=>$e->getMessage()],500);
        }
    }

    /* =====================  RADIUS PROVISION HELPERS  ===================== */

    protected function runRadiusClientEnsure(string $name, string $ip, string $secret): void
    {
        if (env('RADIUS_LOCAL_CALL', true)) {
            $cmd = ['sudo','/usr/local/bin/radius-client-ensure.sh','ensure',$name,$ip,$secret];
            $line = implode(' ', array_map('escapeshellarg', $cmd));
            Process::fromShellCommandline($line)->mustRun();
        } else {
            $sshUser = env('RADIUS_SSH_USER','www-data');
            $host    = env('RADIUS_HOST','127.0.0.1');
            $remote  = sprintf(
                'sudo /usr/local/bin/radius-client-ensure.sh ensure %s %s %s',
                escapeshellarg($name), escapeshellarg($ip), escapeshellarg($secret)
            );
            $cmdline = sprintf('ssh -o StrictHostKeyChecking=yes %s@%s %s', $sshUser, $host, $remote);
            Process::fromShellCommandline($cmdline)->mustRun();
        }
    }

    public function provisionRadiusAndRouter(Mikrotik $mikrotik)
    {
        $this->authorize('update', $mikrotik);
        if (!auth()->check()) abort(401);

        $secret = $mikrotik->radius_secret ?: Str::random((int)env('RADIUS_DEFAULT_SECRET_LENGTH',24));
        if (!$mikrotik->radius_secret) {
            $mikrotik->radius_secret = $secret;
            $mikrotik->save();
        }

        $clientName = 'mk-'.$mikrotik->id;
        $routerIp   = $mikrotik->host;
        $this->runRadiusClientEnsure($clientName, $routerIp, $secret);

        $services = env('RADIUS_SERVICES','ppp,login');
        $authPort = (int)env('RADIUS_AUTH_PORT',1812);
        $acctPort = (int)env('RADIUS_ACCT_PORT',1813);
        $coaPort  = (int)env('RADIUS_COA_PORT',3799);
        $interim  = env('RADIUS_INTERIM','5m');
        $radiusIp = env('RADIUS_HOST','127.0.0.1');

        try {
            $c = new Client([
                'host'=>$mikrotik->host,'user'=>$mikrotik->username,'pass'=>$mikrotik->password,
                'port'=>$mikrotik->port ?: 8728,'timeout'=>8,'attempts'=>1,
            ]);

            // upsert /radius
            $print = (new Query('/radius/print'))->where('address',$radiusIp)->equal('.proplist','.id');
            $found = $c->query($print)->read();

            if (!empty($found)) {
                $set = (new Query('/radius/set'))
                    ->equal('.id',$found[0]['.id'])
                    ->equal('secret',$secret)
                    ->equal('authentication-port',(string)$authPort)
                    ->equal('accounting-port',(string)$acctPort)
                    ->equal('service',$services);
                $c->query($set)->read();
            } else {
                $add = (new Query('/radius/add'))
                    ->equal('address',$radiusIp)
                    ->equal('secret',$secret)
                    ->equal('authentication-port',(string)$authPort)
                    ->equal('accounting-port',(string)$acctPort)
                    ->equal('service',$services)
                    ->equal('comment','Provisioned by Laravel');
                $c->query($add)->read();
            }

            // PPP AAA + CoA
            $c->query((new Query('/ppp/aaa/set'))
                ->equal('use-radius','yes')
                ->equal('interim-update',$interim)
            )->read();

            $c->query((new Query('/radius/incoming/set'))
                ->equal('accept','yes')
                ->equal('port',(string)$coaPort)
            )->read();

            return back()->with('ok','RADIUS siap untuk '.$mikrotik->name);
        } catch (\Throwable $e) {
            \Log::error('[RADIUS APPLY] '.$e->getMessage());
            return back()->withErrors('Gagal apply RADIUS: '.$e->getMessage());
        }

    }

    /*================ Dropdown =================== */

    public function interfacesJson(Mikrotik $mikrotik)
    {
        $this->authorize('view', $mikrotik);
        try {
            $svc = new \App\Services\RouterOSService($mikrotik);
            $ifs = $svc->interfaces();
            $names = [];
            foreach ($ifs as $row) {
                if (!empty($row['name'])) $names[] = $row['name'];
            }
            return response()->json($names);
        } catch (\Throwable $e) {
            return response()->json([], 200);
        }
    }

}
