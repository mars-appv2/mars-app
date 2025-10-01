<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscriptions')) return;

        // plan_id & mikrotik_id dibolehkan NULL
        try { DB::statement("ALTER TABLE `subscriptions` MODIFY `plan_id` BIGINT UNSIGNED NULL"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `subscriptions` MODIFY `mikrotik_id` BIGINT UNSIGNED NULL"); } catch (\Throwable $e) {}

        // status wajib ada & default 'active'
        if (!Schema::hasColumn('subscriptions','status')) {
            try { DB::statement("ALTER TABLE `subscriptions` ADD `status` VARCHAR(20) NOT NULL DEFAULT 'active'"); } catch (\Throwable $e) {}
        } else {
            try { DB::statement("ALTER TABLE `subscriptions` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'active'"); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // biarkan kosong (tidak perlu rollback paksa)
    }
};
