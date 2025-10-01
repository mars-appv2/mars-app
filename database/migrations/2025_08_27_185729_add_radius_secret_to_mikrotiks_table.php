<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('mikrotiks', 'radius_secret')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                $table->string('radius_secret', 128)->nullable();
            });
        }
        if (Schema::hasColumn('mikrotiks', 'host')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                $table->index('host');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mikrotiks', 'radius_secret')) {
            Schema::table('mikrotiks', function (Blueprint $table) {
                $table->dropColumn('radius_secret');
            });
        }
    }
};
