<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    public static function log(array $data)
    {
        try {
            AuditLog::create($data);
        } catch (\Exception $e) {
            // Optional: simpan ke file log jika gagal
            \Log::error('Gagal mencatat audit log: ' . $e->getMessage());
        }
    }
}
