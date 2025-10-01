<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Pakai ALTER langsung supaya tidak perlu doctrine/dbal
        DB::statement("ALTER TABLE `invoices` MODIFY `total` INT NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        // optional rollback:
        // DB::statement("ALTER TABLE `invoices` MODIFY `total` INT NOT NULL");
    }
};
