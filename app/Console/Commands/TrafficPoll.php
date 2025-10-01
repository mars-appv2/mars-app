<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrafficPoll extends Command
{
    protected $signature = 'traffic:poll {--id=}';
    protected $description = 'Poll MikroTik traffic & render RRD PNGs (PHP 7.4 safe)';

    public function handle()
    {
        $q = DB::table('traffic_targets')->where('enabled', 1);
        if (!is_null($this->option('id'))) {
            $q->where('id', (int) $this->option('id'));
        }
        $targets = $q->get();
        if ($targets->isEmpty()) { $this->info('No targets.'); return 0; }

        foreach ($targets as $t) {
            $mk = DB::table('mikrotiks')->where('id', $t->mikrotik_id)->first();
            if (!$mk) { $this->warn("Skip {$t->id} (device missing)"); continue; }

            // --- Connect ke ROUTER (bukan IP target)
            try {
                $client = new \RouterOS\Client([
                    'host'     => $mk->host,
                    'user'     => $mk->username,
                    'pass'     => $mk->password,
                    'port'     => $mk->port ?: 8728,
                    'timeout'  => 5,
                    'attempts' => 1,
                ]);
            } catch (\Throwable $e) {
                $this->error("Connect {$mk->host} fail: ".$e->getMessage());
                continue;
            }

            // --- Ambil rx/tx
            $rx = 0; $tx = 0;
            try {
                if ($t->target_type === 'interface') {
                    // Interface langsung monitor
                    $q = (new \RouterOS\Query('/interface/monitor-traffic'))
                        ->equal('interface', $t->target_key)
                        ->equal('once', 'true');
                    $res = $client->query($q)->read();
                    $rx  = (int)($res && isset($res[0]['rx-bits-per-second']) ? $res[0]['rx-bits-per-second'] : 0);
                    $tx  = (int)($res && isset($res[0]['tx-bits-per-second']) ? $res[0]['tx-bits-per-second'] : 0);

                } else {
                    // IP / PPPoE -> coba baca Simple Queue via /queue/simple print stats
                    list($rx, $tx) = $this->readQueueRate($client, $t);

                    // Fallback PPPoE: jika masih 0 dan bukan IP, cari interface aktif PPPoE
                    if ($rx === 0 && $tx === 0 && $t->target_type === 'pppoe') {
                        $iface = $this->findPppoeInterface($client, $t->target_key);
                        if ($iface) {
                            $q = (new \RouterOS\Query('/interface/monitor-traffic'))
                                ->equal('interface', $iface)->equal('once','true');
                            $res = $client->query($q)->read();
                            $rx  = (int)($res[0]['rx-bits-per-second'] ?? 0);
                            $tx  = (int)($res[0]['tx-bits-per-second'] ?? 0);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Read {$t->id}: ".$e->getMessage());
            }

            // --- Path RRD (TIDAK diubah untuk menjaga kompatibilitas)
            $group   = $t->target_type === 'interface' ? 'interfaces' : ($t->target_type === 'pppoe' ? 'pppoe' : 'ip');
            $keyBase = $t->target_key ? $t->target_key : ($t->label ? $t->label : (string)$t->id);

            // Untuk IP: gunakan hanya digit agar tetap ip/103682137
            if ($group === 'ip') {
                $key = preg_replace('/[^0-9]/', '', (string) $t->target_key);
                if ($key === '') { $key = Str::slug($keyBase, '_'); }
            } else {
                $key = Str::slug($keyBase, '_');
            }

            $rrdDir = storage_path('app/traffic/rrd/'.$group.'/'.$key);
            if (!is_dir($rrdDir)) @mkdir($rrdDir, 0775, true);
            $rrd = $rrdDir.'/traffic.rrd';

            if (!is_file($rrd)) {
                $this->exec([
                    'rrdtool','create',$rrd,'--step','60',
                    'DS:rx:GAUGE:180:0:U','DS:tx:GAUGE:180:0:U',
                    'RRA:AVERAGE:0.5:1:2880',
                    'RRA:AVERAGE:0.5:5:4032',
                    'RRA:AVERAGE:0.5:30:4320',
                    'RRA:AVERAGE:0.5:120:8760',
                ]);
            }

            $this->exec(['rrdtool','update',$rrd,'N:'.$rx.':'.$tx]);

            // PNG
            $pngDir = storage_path('app/traffic/rrd/png/'.$group.'/'.$key);
            if (!is_dir($pngDir)) @mkdir($pngDir, 0775, true);
            $this->graph($rrd, $pngDir.'/day.png',   '-1d',  $keyBase);
            $this->graph($rrd, $pngDir.'/week.png',  '-1w',  $keyBase);
            $this->graph($rrd, $pngDir.'/month.png', '-1m',  $keyBase);
            $this->graph($rrd, $pngDir.'/year.png',  '-1y',  $keyBase);

            $this->info("Updated {$group}/{$key} rx={$rx} tx={$tx}");
        }

        return 0;
    }

    // ---------------- helpers ----------------

    /**
     * Ambil rx/tx dari Simple Queue.
     * Prefer name (queue_name/label), fallback ke target IP (append /32).
     * Rate pada Mikrotik adalah "tx/rx" -> kita kembalikan (rx, tx).
     */
    private function readQueueRate(\RouterOS\Client $client, $t) : array
    {
        $rows = [];

        // 1) By explicit queue name (kalau ada)
        if (!empty($t->queue_name)) {
            $q = (new \RouterOS\Query('/queue/simple/print'))->add('|stats');
            $q->where('name', $t->queue_name);
            $rows = $client->query($q)->read();
        }

        // 2) By label atau target_key sebagai name
        if (!$rows) {
            $name = $t->label ?: $t->target_key;
            $q = (new \RouterOS\Query('/queue/simple/print'))->add('|stats');
            $q->where('name', $name);
            $rows = $client->query($q)->read();
        }

        // 3) Fallback by target (IP/CIDR)
        if (!$rows) {
            $target = $t->target_key;
            if ($t->target_type === 'ip' && strpos($target, '/') === false) {
                $target .= '/32';
            }
            $q = (new \RouterOS\Query('/queue/simple/print'))->add('|stats');
            $q->where('target', $target);
            $rows = $client->query($q)->read();
        }

        $rx = 0; $tx = 0;
        if ($rows) {
            $r = $rows[0];
            // Prefer angka langsung jika tersedia
            if (isset($r['rx-bits-per-second']) || isset($r['tx-bits-per-second'])) {
                $rx = (int)($r['rx-bits-per-second'] ?? 0);
                $tx = (int)($r['tx-bits-per-second'] ?? 0);
            } elseif (!empty($r['rate'])) {
                // rate pada print stats biasanya "TX/RX" (contoh: 39.1Mbps/476.0Mbps)
                $parts = explode('/', strtolower($r['rate']));
                $tx = $this->toBps($parts[0] ?? '0bps');
                $rx = $this->toBps($parts[1] ?? '0bps');
            }
        }

        return [$rx, $tx];
    }

    private function findPppoeInterface(\RouterOS\Client $client, string $user)
    {
        try {
            $q = (new \RouterOS\Query('/ppp/active/print'))->where('name', $user);
            $act = $client->query($q)->read();
            return $act[0]['interface'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toBps(string $s) : int
    {
        $s = trim($s);
        if ($s === '' || $s === '0' || $s === '0bps') return 0;

        if (stripos($s, 'gbps') !== false) return (int) round((float) $s * 1000 * 1000 * 1000);
        if (stripos($s, 'mbps') !== false) return (int) round((float) $s * 1000 * 1000);
        if (stripos($s, 'kbps') !== false) return (int) round((float) $s * 1000);
        if (stripos($s, 'bps')  !== false) return (int) preg_replace('/[^0-9]/', '', $s);
        return (int) $s;
    }

    private function graph($rrd, $out, $start, $title)
    {
        $this->exec([
            'rrdtool','graph',$out,'--start',$start,
            '--title',$title,'--vertical-label','bits per second',
            '--width','900','--height','260','--lower-limit','0',
            'DEF:rx='.$rrd.':rx:AVERAGE','DEF:tx='.$rrd.':tx:AVERAGE',
            'AREA:rx#2DD4BF:Download','LINE1:rx#0EA5A5',
            'AREA:tx#60A5FA:Upload:STACK','LINE1:tx#3B82F6',
            'COMMENT:\\n',
            'GPRINT:rx:MAX:Max In\\: %6.2lf %Sb',
            'GPRINT:rx:AVERAGE:Avg In\\: %6.2lf %Sb',
            'GPRINT:rx:LAST:Cur In\\: %6.2lf %Sb\\n',
            'GPRINT:tx:MAX:Max Out\\: %6.2lf %Sb',
            'GPRINT:tx:AVERAGE:Avg Out\\: %6.2lf %Sb',
            'GPRINT:tx:LAST:Cur Out\\: %6.2lf %Sb\\n',
        ]);
    }

    private function exec(array $parts)
    {
        $cmd = [];
        foreach ($parts as $p) $cmd[] = escapeshellarg($p);
        @exec(implode(' ', $cmd), $o, $rc);
    }
}
