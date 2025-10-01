<?php

namespace App\Services;

use App\Models\Mikrotik;

class RadiusCoaService
{
    private $radclient;
    private $port;

    public function __construct(string $radclientBin = '/usr/bin/radclient', int $port = 3799)
    {
        $this->radclient = $radclientBin;
        $this->port      = $port;
    }

    /** Kirim CoA: set group (mapping ke PPP profile / policy dynamic) */
    public function sendCoaGroup(Mikrotik $mk, string $username, string $groupName, ?string $framedIp = null): bool
    {
        $attrs = [
            'User-Name'       => $username,
            'NAS-IP-Address'  => $mk->host,
            'Mikrotik-Group'  => $groupName,
        ];
        if (!empty($framedIp)) $attrs['Framed-IP-Address'] = $framedIp;
        return $this->radclient($mk, 'coa', $attrs);
    }

    /** Kirim CoA: rate limit, contoh: "5M/5M 5M/5M 0/0 1/1" (up/down burst dll) */
    public function sendCoaRateLimit(Mikrotik $mk, string $username, string $rateLimit, ?string $framedIp = null): bool
    {
        $attrs = [
            'User-Name'           => $username,
            'NAS-IP-Address'      => $mk->host,
            'Mikrotik-Rate-Limit' => $rateLimit,
        ];
        if (!empty($framedIp)) $attrs['Framed-IP-Address'] = $framedIp;
        return $this->radclient($mk, 'coa', $attrs);
    }

    /** Putuskan koneksi (Disconnect-Request) supaya re-auth ambil policy baru */
    public function disconnect(Mikrotik $mk, string $username, ?string $framedIp = null): bool
    {
        $attrs = [
            'User-Name'      => $username,
            'NAS-IP-Address' => $mk->host,
        ];
        if (!empty($framedIp)) $attrs['Framed-IP-Address'] = $framedIp;
        return $this->radclient($mk, 'disconnect', $attrs);
    }

    /** ====== Internal helper ====== */
    private function radclient(Mikrotik $mk, string $action, array $attrs): bool
    {
        // Ambil shared secret untuk CoA:
        // 1) kalau model Mikrotik punya kolom radius_secret → pakai itu
        // 2) kalau tidak → pakai env global MIKROTIK_RADIUS_SECRET
        $secret = null;
        try {
            if (property_exists($mk, 'radius_secret') && !empty($mk->radius_secret)) {
                $secret = (string) $mk->radius_secret;
            }
        } catch (\Throwable $e) {}
        if (!$secret) {
            $secret = (string) env('MIKROTIK_RADIUS_SECRET', '');
        }
        if ($secret === '') return false;

        $host = escapeshellarg($mk->host . ':' . $this->port);
        $secretArg = escapeshellarg($secret);
        $action = ($action === 'disconnect') ? 'disconnect' : 'coa';

        // Bangun payload attribute
        $lines = [];
        foreach ($attrs as $k => $v) {
            $vv = addcslashes((string)$v, "\"\\");
            $lines[] = $k.' = "'.$vv.'"';
        }
        $payload = implode("\n", $lines)."\n";

        $cmd = $this->radclient . ' -x ' . $host . ' ' . $action . ' ' . $secretArg;
        $des = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $des, $pipes, null, null);
        if (!is_resource($proc)) return false;

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);

        $code = proc_close($proc);

        if ($code !== 0) {
            \Log::warning('radclient '.$action.' fail: code='.$code.' out='.$out.' err='.$err);
            return false;
        }
        \Log::info('radclient '.$action.' OK: '.$out);
        return true;
    }
}
