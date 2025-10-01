<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `subscriptions` MODIFY `plan_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `subscriptions` MODIFY `mikrotik_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `subscriptions` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        // rollback (opsional) – hati-hati kalau ada data NULL:
        // DB::statement("ALTER TABLE `subscriptions` MODIFY `plan_id` BIGINT UNSIGNED NOT NULL");
        // DB::statement("ALTER TABLE `subscriptions` MODIFY `mikrotik_id` BIGINT UNSIGNED NOT NULL");
    }
};
