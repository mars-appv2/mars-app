<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function add(array $payload): void
    {
        try {
            AuditLog::create($payload);
        } catch (\Throwable $e) {
            // jangan ganggu request utama
            \Log::error('[AUDIT_ERR] write failed: '.$e->getMessage());
        }
    }

    public static function fromRequest(Request $r, array $opt = []): void
    {
        $u = $r->user();

        // sanitize payload (hapus/masking field sensitif)
        $input = $r->all();
        $maskKeys = ['password','pass','secret','token','_token','password_confirmation'];
        foreach ($maskKeys as $k) {
            if (array_key_exists($k, $input)) {
                $input[$k] = '***';
            }
        }

        $row = [
            'user_id'    => $u?->id,
            'user_name'  => $u?->name,
            'user_email' => $u?->email,
            'user_roles' => is_array($u?->roles ?? null) ? $u->roles : [],

            'method'     => $r->getMethod(),
            'route'      => $r->route()?->getName(),
            'path'       => $r->path(),
            'ip'         => $r->ip(),
            'ua'         => substr((string)$r->userAgent(), 0, 250),

            'action'      => $opt['action']      ?? null,
            'target_type' => $opt['target_type'] ?? null,
            'target_key'  => $opt['target_key']  ?? null,
            'status'      => $opt['status']      ?? 'ok',
            'status_code' => $opt['status_code'] ?? null,
            'message'     => $opt['message']     ?? null,
            'data'        => $opt['data']        ?? $input,
        ];

        self::add($row);
    }
}
