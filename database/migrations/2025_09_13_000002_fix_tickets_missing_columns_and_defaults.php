<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tickets')) return;

        // Tambah kolom yang belum ada
        Schema::table('tickets', function (Blueprint $t) {
            if (!Schema::hasColumn('tickets','subject'))     $t->string('subject')->nullable()->after('id');
            if (!Schema::hasColumn('tickets','description')) $t->text('description')->nullable()->after('subject');
            if (!Schema::hasColumn('tickets','status'))      $t->string('status')->default('open')->index()->after('description');
            if (!Schema::hasColumn('tickets','priority'))    $t->string('priority')->default('normal')->after('status');
            if (!Schema::hasColumn('tickets','code'))        $t->string('code', 40)->nullable()->unique()->after('id');
        });

        // Pastikan kolom code punya DEFAULT (hindari error 1364 pada MySQL)
        try {
            // Set default '' (string kosong) agar insert tanpa code tidak error
            DB::statement("ALTER TABLE `tickets` ALTER `code` SET DEFAULT ''");
        } catch (\Throwable $e) {
            // Jika ALTER gagal (engine beda), kita biarkan nullable (sudah ditambah di atas)
        }
    }

    public function down(): void
    {
        // tidak rollback agar aman
    }
};
