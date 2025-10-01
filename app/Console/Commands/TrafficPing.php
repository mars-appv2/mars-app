<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class TrafficPing extends Command
{
    protected $signature = 'traffic:ping {--id=}';
    protected $description = 'Ping content targets, record latency, write RRD (downtime %) and graphs';

    public function handle()
    {
        if (!Schema::hasTable('traffic_content_map')) {
            $this->warn('traffic_content_map not found'); return 0;
        }

        $q = DB::table('traffic_content_map')->where('enabled',1);
        if ($this->option('id')) $q->where('id',(int)$this->option('id'));
        $apps = $q->orderBy('name')->get();

        foreach ($apps as $a) {
            $ip = trim((string)$a->cidr);
            if ($ip === '' || strpos($ip,'/') !== false) {
                // fokus IP tunggal; CIDR di-skip
                continue;
            }

            list($ok, $rtt) = $this->pingOne($ip);

            DB::table('traffic_latency')->insert(array(
                'name'       => $a->name,
                'target_ip'  => $ip,
                'success'    => $ok ? 1 : 0,
                'rtt_ms'     => $ok ? (int)$rtt : null,
                'created_at' => now(),
            ));

            // nilai downtime% sampel saat ini: 0 (sukses) / 100 (timeout)
            $percent = $ok ? 0 : 100;
            $this->rrdUpdate($a, $percent);
        }
        return 0;
    }

    private function pingOne($ip)
    {
        $cmd = sprintf('ping -c 1 -W 1 %s 2>&1', escapeshellarg($ip));
        $out = shell_exec($cmd) ?: '';
        if (strpos($out, '1 received') !== false || strpos($out, '1 packets received') !== false) {
            $ms = null;
            if (preg_match('/time[=<]([\d\.]+)\s*ms/i', $out, $m)) $ms = (int) round((float)$m[1]);
            return array(true, $ms);
        }
        return array(false, null);
    }

    private function rrdPaths($key)
    {
        $rrd = storage_path('app/traffic/rrd/rrd/content/'.$key.'.rrd');
        $pngBase = storage_path('app/traffic/rrd/png/content/'.$key);
        if (!is_dir(dirname($rrd)))     @mkdir(dirname($rrd), 0775, true);
        if (!is_dir($pngBase))          @mkdir($pngBase, 0775, true);
        return array($rrd, $pngBase);
    }

    private function ensureRrd($rrd)
    {
        if (is_file($rrd)) return;
        $step = 60; // 60s
        $cmd = array(
            'rrdtool','create',$rrd,
            'DS:down:GAUGE:180:0:100', // downtime percentage 0..100
            'RRA:AVERAGE:0.5:1:1440',  // 1d of 1-minute
            'RRA:AVERAGE:0.5:30:336',  // ~1w of 30-min
            'RRA:AVERAGE:0.5:120:372', // ~31d of 2-hour
            'RRA:AVERAGE:0.5:1440:395' // ~1y of 1-day
        );
        shell_exec(implode(' ', array_map('escapeshellarg', $cmd)));
    }

    private function rrdUpdate($app, $percent)
    {
        $key = \Illuminate\Support\Str::slug($app->cidr ?: $app->name, '_');
        list($rrd, $pngBase) = $this->rrdPaths($key);
        $this->ensureRrd($rrd);

        $upd = array('rrdtool','update',$rrd,'N:'.(int)$percent);
        shell_exec(implode(' ', array_map('escapeshellarg',$upd)));

        $title = ($app->name ?: 'Content').' — Downtime %';
        $defs  = array(
            "DEF:d=$rrd:down:AVERAGE",
            "CDEF:capped=d,0,100,LIMIT",
            "VDEF:avg=capped,AVERAGE",
            "VDEF:max=capped,MAXIMUM",
            "VDEF:min=capped,MINIMUM",
        );

        $this->graph("$pngBase/day.png",   "-1d",  "Daily",   $title, $defs);
        $this->graph("$pngBase/week.png",  "-1w",  "Weekly",  $title, $defs);
        $this->graph("$pngBase/month.png", "-1m",  "Monthly", $title, $defs);
        $this->graph("$pngBase/year.png",  "-1y",  "Yearly",  $title, $defs);
    }

    private function graph($outfile, $range, $caption, $title, $defs)
    {
        $cmd = array_merge(
            array('rrdtool','graph',$outfile,'--start',$range,'--vertical-label','% downtime',
                  '--lower-limit','0','--upper-limit','100','--rigid',
                  '--width','900','--height','220',
                  '--title', $caption.' '.$title),
            $defs,
            array(
                'AREA:capped#FF4444:Downtime %',
                'LINE1:capped#DD0000',
                'GPRINT:avg: Avg\\: %5.1lf%%',
                'GPRINT:min: Min\\: %5.1lf%%',
                'GPRINT:max: Max\\: %5.1lf%%\\l'
            )
        );
        shell_exec(implode(' ', array_map('escapeshellarg',$cmd)));
    }
}
