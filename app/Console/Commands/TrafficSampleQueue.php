<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Mikrotik;
use RouterOS\Client;
use RouterOS\Query;

class TrafficSampleQueue extends Command
{
    protected $signature = 'traffic:sample-queue';
    protected $description = 'Pull rate Simple Queue untuk target IP dan simpan ke traffic_samples';

    public function handle(): int
    {
        $targets = DB::table('traffic_targets')
            ->where('enabled',1)
            ->where('target_type','ip')
            ->select('id','mikrotik_id','target_key','queue_name')
            ->get();

        foreach ($targets as $t) {
            $m = Mikrotik::find($t->mikrotik_id);
            if (!$m) continue;

            $qname = $t->queue_name ?: ('md-'.preg_replace('/\/\d+$/','',$t->target_key));

            try{
                $c = new Client([
                    'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                    'port'=>$m->port ?: 8728,'timeout'=>5,'attempts'=>1
                ]);

                $q = (new Query('/queue/simple/print'))
                    ->where('name',$qname)->equal('.proplist','rate');
                $res = $c->query($q)->read();

                $rx = 0; $tx = 0;
                if (!empty($res) && !empty($res[0]['rate'])) {
                    $rate = $res[0]['rate'];
                    $pos  = strpos($rate,'/');
                    $rx   = (int)substr($rate,0,$pos);
                    $tx   = (int)substr($rate,$pos+1);
                }

                DB::table('traffic_samples')->insert([
                    'mikrotik_id' => $t->mikrotik_id,
                    'target'      => $t->target_key,
                    'rx_bps'      => $rx,
                    'tx_bps'      => $tx,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }catch(\Throwable $e){
                \Log::warning("sample-queue {$qname} fail: ".$e->getMessage());
            }
        }

        $this->info('OK');
        return 0;
    }
}
