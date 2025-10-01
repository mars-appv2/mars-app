<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class RadiusHealth
{
    public function check()
    {
        $host    = getenv('RADIUS_HOST') ?: env('RADIUS_HOST');
        $secret  = getenv('RADIUS_SECRET') ?: env('RADIUS_SECRET');
        $timeout = (int) (getenv('RADIUS_TIMEOUT') ?: env('RADIUS_TIMEOUT', 5));

        if (!$host || !$secret) {
            return ['ok' => false, 'msg' => 'RADIUS_HOST/SECRET belum di-set di .env'];
        }

        // ABSOLUTE PATHS biar gak tergantung PATH FPM
        $radclient = '/usr/bin/radclient';

        if (!is_executable($radclient)) {
            return ['ok' => false, 'msg' => "$radclient tidak bisa dieksekusi (install freeradius-utils?)"];
        }

        // Jalankan radclient tanpa shell; stdin di-set dari PHP
        $args = [$radclient, '-x', $host . ':1812', 'status', $secret];

        $process = new Process($args);
        $process->setTimeout($timeout);          // timeout detik
        $process->setInput("Message-Authenticator = 0x00\n"); // kirim ke stdin
        $process->run();

        $out = $process->getOutput() . $process->getErrorOutput();
        $ok  = $process->isSuccessful() && preg_match('/Received|Access-Accept|Status-Server/i', $out);

        return ['ok' => $ok, 'msg' => trim($out)];
    }
}
