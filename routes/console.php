<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
| File ini dipanggil oleh Artisan. Di sini kita bisa mendaftarkan
| command berbasis closure tanpa harus edit Kernel.php.
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ================== REBUILD PPP SECRETS DARI RADIUS ==================
use Illuminate\Support\Facades\DB;
use App\Models\Mikrotik;
use RouterOS\Client;
use RouterOS\Query;

/**
 * Recreate/ensure PPP secrets pada Mikrotik dari DB RADIUS:
 * - Password dari radcheck (Cleartext-Password)
 * - Profile dari radusergroup.groupname (fallback "default" jika tidak ada/unknown)
 * - Disabled="yes" jika ada Auth-Type := Reject
 *
 * Contoh:
 *   php artisan ppp:rebuild-secrets 7 --dry   # lihat aksi saja
 *   php artisan ppp:rebuild-secrets 7         # eksekusi ke router
 */
Artisan::command('ppp:rebuild-secrets {mikrotik_id} {--dry}', function ($mikrotik_id) {
    $dry = (bool) $this->option('dry');

    $m = Mikrotik::find((int)$mikrotik_id);
    if (!$m) { $this->error('Mikrotik not found: '.$mikrotik_id); return 1; }

    $this->info("Connecting to Mikrotik {$m->name} ({$m->host}) ...");
    try {
        $c = new Client([
            'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
            'port'=>$m->port ?: 8728,'timeout'=>10,'attempts'=>1,
        ]);
    } catch (\Throwable $e) {
        $this->error('ROS connect failed: '.$e->getMessage());
        return 2;
    }

    // ambil daftar profile yang ada
    $profiles = [];
    try {
        $rows = $c->query((new Query('/ppp/profile/print'))->equal('.proplist','name'))->read();
        foreach ($rows as $r) { if (!empty($r['name'])) $profiles[$r['name']] = true; }
    } catch (\Throwable $e) {}

    $count=0; $added=0; $updated=0; $failed=0;

    $this->info('Rebuilding secrets from RADIUS...');

    DB::connection('radius')->table('radcheck')
        ->select('id','username','value')
        ->where('attribute','Cleartext-Password')
        ->orderBy('id')
        ->chunk(500, function($rows) use ($c, $profiles, $dry, &$count, &$added, &$updated, &$failed) {

            foreach ($rows as $rc) {
                $u   = (string)$rc->username;
                $pwd = (string)$rc->value;

                // profile/plan
                $grp  = DB::connection('radius')->table('radusergroup')->where('username',$u)->value('groupname');
                $plan = $grp ?: 'default';
                if (!isset($profiles[$plan])) $plan = 'default';

                // disabled? (Auth-Type := Reject)
                $reject = DB::connection('radius')->table('radcheck')->where([
                    ['username','=',$u], ['attribute','=','Auth-Type'], ['op',':='], ['value','=','Reject'],
                ])->exists();
                $disabled = $reject ? 'yes' : 'no';

                $count++;

                try {
                    // cek secret ada?
                    $res = $c->query((new Query('/ppp/secret/print'))->where('name',$u)->equal('.proplist','.id'))->read();

                    if ($dry) {
                        $this->line(($res ? 'SET ' : 'ADD ')."$u profile=$plan disabled=$disabled");
                        continue;
                    }

                    if (empty($res)) {
                        $q = (new Query('/ppp/secret/add'))
                            ->equal('name',$u)->equal('password',$pwd)->equal('profile',$plan)->equal('disabled',$disabled);
                        $c->query($q)->read();
                        $added++;
                    } else {
                        $id = $res[0]['.id'];
                        $q = (new Query('/ppp/secret/set'))
                            ->equal('.id',$id)->equal('password',$pwd)->equal('profile',$plan)->equal('disabled',$disabled);
                        $c->query($q)->read();
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("fail $u : ".$e->getMessage());
                }
            }
        });

    $this->info("Done. processed=$count, added=$added, updated=$updated, failed=$failed");
    return 0;
})->describe('Recreate PPP secrets on Mikrotik from RADIUS');
