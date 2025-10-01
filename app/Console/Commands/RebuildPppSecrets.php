<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Mikrotik;
use RouterOS\Client;
use RouterOS\Query;

class RebuildPppSecrets extends Command
{
    protected $signature = 'ppp:rebuild-secrets {mikrotik_id} {--dry}';
    protected $description = 'Recreate/ensure PPP secrets on Mikrotik from RADIUS DB (radcheck/radusergroup)';

    public function handle()
    {
        $mikId = (int) $this->argument('mikrotik_id');
        $dry   = (bool) $this->option('dry');

        $m = Mikrotik::find($mikId);
        if (!$m) {
            $this->error('Mikrotik not found: '.$mikId);
            return 1;
        }

        $this->info("Connecting to Mikrotik {$m->name} ({$m->host}) ...");
        try {
            $c = new Client([
                'host' => $m->host, 'user' => $m->username, 'pass' => $m->password,
                'port' => $m->port ?: 8728, 'timeout' => 10, 'attempts' => 1,
            ]);
        } catch (\Throwable $e) {
            $this->error('ROS connect failed: '.$e->getMessage());
            return 2;
        }

        // Ambil daftar profile yang ada (untuk fallback)
        $profiles = [];
        try {
            $rows = $c->query((new Query('/ppp/profile/print'))->equal('.proplist','name'))->read();
            foreach ($rows as $r) {
                if (!empty($r['name'])) $profiles[$r['name']] = true;
            }
        } catch (\Throwable $e) {}

        $count=0; $added=0; $updated=0; $failed=0;

        $this->info('Rebuilding secrets from RADIUS...');

        DB::connection('radius')->table('radcheck')
            ->select('id','username','value')
            ->where('attribute','Cleartext-Password')
            ->orderBy('id')
            ->chunk(500, function($rows) use ($c, $profiles, $dry, &$count, &$added, &$updated, &$failed) {
                foreach ($rows as $rc) {
                    $u   = (string) $rc->username;
                    $pwd = (string) $rc->value;

                    // profile dari radusergroup; fallback "default" bila tak ada/unknown
                    $grp  = DB::connection('radius')->table('radusergroup')->where('username',$u)->value('groupname');
                    $plan = $grp ?: 'default';
                    if (!isset($profiles[$plan])) $plan = 'default';

                    // disabled jika punya Auth-Type := Reject
                    $reject = DB::connection('radius')->table('radcheck')->where([
                        ['username','=',$u], ['attribute','=','Auth-Type'], ['op',':='], ['value','=','Reject'],
                    ])->exists();
                    $disabled = $reject ? 'yes' : 'no';

                    $count++;

                    try {
                        // cek secret ada?
                        $res = $c->query(
                            (new Query('/ppp/secret/print'))->where('name',$u)->equal('.proplist','.id')
                        )->read();

                        if ($dry) {
                            $this->line(($res ? 'SET ' : 'ADD ')."$u profile=$plan disabled=$disabled");
                            continue;
                        }

                        if (empty($res)) {
                            // add
                            $q = (new Query('/ppp/secret/add'))
                                ->equal('name',$u)
                                ->equal('password',$pwd)
                                ->equal('profile',$plan)
                                ->equal('disabled',$disabled);
                            $c->query($q)->read();
                            $added++;
                        } else {
                            // set
                            $id = $res[0]['.id'];
                            $q = (new Query('/ppp/secret/set'))
                                ->equal('.id',$id)
                                ->equal('password',$pwd)
                                ->equal('profile',$plan)
                                ->equal('disabled',$disabled);
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
    }
}
