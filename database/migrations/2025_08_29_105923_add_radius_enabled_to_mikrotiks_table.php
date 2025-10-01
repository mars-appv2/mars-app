<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('mikrotiks','radius_enabled')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                // default true supaya behavior lama tetap: semua ikut RADIUS
                $table->boolean('radius_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mikrotiks','radius_enabled')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                $table->dropColumn('radius_enabled');
            });
        }
    }
};
