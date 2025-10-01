<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscriptions')) return;

        // 1) Hapus duplikasi (pertahankan id TERKECIL per (username, mikrotik_id))
        //    Catatan: jika kamu TIDAK mau auto-delete duplikat, silakan comment blok ini.
        try {
            DB::statement("
                DELETE s1 FROM subscriptions s1
                INNER JOIN subscriptions s2
                  ON s1.username = s2.username
                 AND ( (s1.mikrotik_id IS NULL AND s2.mikrotik_id IS NULL)
                    OR (s1.mikrotik_id = s2.mikrotik_id) )
                 AND s1.id > s2.id
            ");
        } catch (\Throwable $e) {
            // biarkan lewat; biasanya aman
        }

        // 2) Tambah UNIQUE INDEX (username, mikrotik_id) bila belum ada
        try {
            $idx = DB::select("SHOW INDEX FROM `subscriptions` WHERE Key_name='subs_username_mk_unique'");
            if (empty($idx)) {
                DB::statement("ALTER TABLE `subscriptions`
                    ADD UNIQUE KEY `subs_username_mk_unique` (`username`,`mikrotik_id`)");
            }
        } catch (\Throwable $e) {
            // kalau gagal (misal versi MySQL lama), tampilkan error saat migrate
            throw $e;
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscriptions')) return;

        // Drop UNIQUE INDEX jika ada
        try {
            $idx = DB::select("SHOW INDEX FROM `subscriptions` WHERE Key_name='subs_username_mk_unique'");
            if (!empty($idx)) {
                DB::statement("ALTER TABLE `subscriptions` DROP INDEX `subs_username_mk_unique`");
            }
        } catch (\Throwable $e) {
            // abaikan
        }
    }
};
