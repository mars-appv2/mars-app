<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('plans')) return;

        // Tambah kolom jika belum ada
        if (!Schema::hasColumn('plans', 'price_month')) {
            DB::statement("ALTER TABLE `plans`
                ADD COLUMN `price_month` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `price`");
        }

        // Kunci NOT NULL + DEFAULT 0 (tanpa Doctrine DBAL)
        try { DB::statement("ALTER TABLE `plans`
            MODIFY `price` DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `plans`
            MODIFY `price_month` DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // rollback opsional—biarkan apa adanya
    }
};
