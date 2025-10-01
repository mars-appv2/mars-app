<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('tickets', 'subject'))    $table->string('subject')->nullable()->after('id');
                if (!Schema::hasColumn('tickets', 'description'))$table->text('description')->nullable()->after('subject');
                if (!Schema::hasColumn('tickets', 'status'))     $table->string('status')->default('open')->index()->after('description');
                if (!Schema::hasColumn('tickets', 'priority'))   $table->string('priority')->default('normal')->after('status');
            });
        }
    }

    public function down(): void
    {
        // tidak rollback kolom agar aman
    }
};
