<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaService
{
    private string $base;

    public function __construct(?string $base = null)
    {
        // Ambil dari config/services.php → .env: WA_GATEWAY_URL
        $this->base = rtrim(
            $base ?? config('services.wa_gw.url', env('WA_GATEWAY_URL', 'http://127.0.0.1:3900')),
            '/'
        );
    }

    private function toJid(string $to): string
    {
        $t = trim($to);
        if ($t === '') return '';
        if (preg_match('/@s\.whatsapp\.net$/', $t)) return $t;

        // normalisasi msisdn
        $digits = preg_replace('/\D/', '', $t);
        if ($digits === '') return '';
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits . '@s.whatsapp.net';
    }

    public function sendText(string $to, string $text): bool
    {
        $jid = $this->toJid($to);
        $text = ltrim($text ?? '');
        if ($jid === '' || $text === '') return false;

        // (opsional) sisipkan header dari .env
        $addHeader = filter_var(env('WA_HEADER_ENABLE', false), FILTER_VALIDATE_BOOL);
        $header    = trim((string) env('WA_MSG_HEADER', ''));
        if ($addHeader && $header !== '') {
            $text = '*' . $header . "*\n" . $text;
        }

        try {
            // === ENDPOINT BARU ===
            $res = Http::timeout(8)->post($this->base . '/sendText', [
                'to'   => $jid,
                'text' => $text,
            ]);

            if (!$res->ok()) {
                Log::warning('[WA] sendText fail', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                    'to'     => $jid,
                    'base'   => $this->base,
                ]);
            }
            return $res->ok();
        } catch (\Throwable $e) {
            Log::warning('[WA] sendText err', ['err' => $e->getMessage(), 'to' => $jid, 'base' => $this->base]);
            return false;
        }
    }

    public function sendMany(array $phones, string $text): int
    {
        $ok = 0;
        foreach ($phones as $p) {
            if ($this->sendText($p, $text)) $ok++;
            usleep(200 * 1000); // 0.2s jeda
        }
        return $ok;
    }
}
