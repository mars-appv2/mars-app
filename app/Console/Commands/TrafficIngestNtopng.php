<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrafficIngestNtopng extends Command
{
    protected $signature='traffic:ingest-ntopng {--since=1h}';
    protected $description='Ingest application/host traffic from ntopng into traffic_app_hourly and render MRTG-style graphs';

    public function handle(): int
    {
        $base = env('NTOPNG_URL', 'http://127.0.0.1:3001');
        $bucket = Carbon::now()->minute(0)->second(0);
        $rows = [];

        $apps = $this->curl($base.'/lua/rest/v2/get/flows/app_stats.lua') ?: [];
        if (empty($apps)) $apps = $this->curl($base.'/lua/rest/v2/get/apps/top.lua') ?: [];
        if (is_array($apps)) {
            foreach ($apps as $a) {
                $app = strtolower($a['name'] ?? ($a['app_name'] ?? 'unknown'));
                $bytes = (int)($a['bytes'] ?? $a['traffic'] ?? 0);
                if ($bytes > 0) $rows[] = ['bucket'=>$bucket, 'host_ip'=>'*', 'app'=>$app, 'bytes'=>$bytes];
            }
        }

        $hosts = $this->curl($base.'/lua/rest/v2/get/hosts/top.lua') ?: [];
        foreach ($hosts as $h) {
            $ip = $h['ip'] ?? ($h['host'] ?? null);
            $bytes = (int)($h['bytes'] ?? $h['traffic'] ?? 0);
            $app = strtolower($h['proto'] ?? 'total');
            if ($ip && $bytes > 0) $rows[] = ['bucket'=>$bucket, 'host_ip'=>$ip, 'app'=>$app, 'bytes'=>$bytes];
        }

        foreach ($rows as $r) {
            DB::table('traffic_app_hourly')->insert($r);
            $bps = max(0, (int)($r['bytes'] * 8 / 3600));
            if ($r['host_ip'] !== '*') {
                $this->rrdUpdateAndGraph($r['host_ip'], $bps, storage_path('app/traffic/pppoe'), 'PPPoE IP '.$r['host_ip']);
            }
            $this->rrdUpdateAndGraph($r['app'], $bps, storage_path('app/traffic/apps'), 'App '.$r['app']);
        }

        $this->info('Ingested '.count($rows).' rows @ '.$bucket);
        return 0;
    }

    private function rrdUpdateAndGraph($key, $bps, $baseDir, $title)
    {
        $keySafe = preg_replace('~[^A-Za-z0-9_.-]+~','_', $key);
        $rrdDir = $baseDir.'/rrd';
        $pngDir = $baseDir.'/png/'.$keySafe;
        if (!is_dir($rrdDir)) mkdir($rrdDir, 0775, true);
        if (!is_dir($pngDir)) mkdir($pngDir, 0775, true);

        $rrd = $rrdDir.'/'.$keySafe.'.rrd';
        if (!file_exists($rrd)) {
            shell_exec(sprintf('rrdtool create %s --step 300 DS:rate:GAUGE:600:0:U RRA:AVERAGE:0.5:1:288 RRA:AVERAGE:0.5:6:336 RRA:AVERAGE:0.5:24:372', escapeshellarg($rrd)));
        }
        shell_exec(sprintf('rrdtool update %s N:%s', escapeshellarg($rrd), $bps));

        $make = function($start, $png) use ($rrd, $title) {
            $cmd = [
                'rrdtool','graph',$png,'--start',$start,'--end','now',
                '--title',$title,'--vertical-label','bits per second',
                '--lower-limit','0','--units=si',
                'DEF:rate='.$rrd.':rate:AVERAGE',
                'AREA:rate#32CD32:Traffic',
                'VDEF:rmax=rate,MAXIMUM','VDEF:ravg=rate,AVERAGE','VDEF:rcur=rate,LAST',
                'GPRINT:rmax:Max\\:%6.2lf%s','GPRINT:ravg:Avg\\:%6.2lf%s','GPRINT:rcur:Cur\\:%6.2lf%s\\n',
                'VRULE:now#FF0000:'
            ];
            shell_exec(implode(' ', array_map('escapeshellarg', $cmd)));
        };
        $make('-1d', $pngDir.'/day.png');
        $make('-1w', $pngDir.'/week.png');
        $make('-1m', $pngDir.'/month.png');
        $make('-1y', $pngDir.'/year.png');
    }

    private function curl($url){
        $out = shell_exec("curl -m 5 -fsSL ".escapeshellarg($url));
        return $out ? json_decode($out, true) : null;
    }
}
