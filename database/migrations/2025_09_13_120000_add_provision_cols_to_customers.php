<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) return;

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers','provision_status')) {
                $table->string('provision_status',20)->default('pending')->after('note'); // pending|accepted|rejected
            }
            if (!Schema::hasColumn('customers','accepted_by')) {
                $table->unsignedBigInteger('accepted_by')->nullable()->after('provision_status');
            }
            if (!Schema::hasColumn('customers','accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            }
        });
    }

    public function down(): void
    {
        // aman: biarkan kolom tetap ada jika rollback
    }
};

