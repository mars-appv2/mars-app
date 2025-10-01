<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Map angka lama ke string (jika sebelumnya 0/1/2)
        try {
            DB::statement("
                UPDATE audit_logs
                SET status = CASE status
                    WHEN '1' THEN 'success'
                    WHEN '2' THEN 'warning'
                    WHEN '0' THEN 'error'
                    ELSE status
                END
                WHERE status REGEXP '^[0-9]+$'
            ");
        } catch (\Throwable $e) {
            // abaikan, lanjut alter
        }

        // Ubah kolom status jadi ENUM; jika gagal, fallback ke VARCHAR
        try {
            DB::statement("ALTER TABLE audit_logs MODIFY status ENUM('success','error','warning') NOT NULL DEFAULT 'success'");
        } catch (\Throwable $e) {
            DB::statement("ALTER TABLE audit_logs MODIFY status VARCHAR(20) NOT NULL DEFAULT 'success'");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE audit_logs MODIFY status VARCHAR(20) NOT NULL DEFAULT 'success'");
    }
};
