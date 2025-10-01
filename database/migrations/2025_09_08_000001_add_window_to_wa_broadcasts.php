<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_broadcasts', function (Blueprint $t) {
            if (!Schema::hasColumn('wa_broadcasts', 'window_minutes')) {
                $t->unsignedSmallInteger('window_minutes')->default(1)->after('rate_per_min');
            }
            if (!Schema::hasColumn('wa_broadcasts', 'window_started_at')) {
                $t->timestamp('window_started_at')->nullable()->after('window_minutes');
            }
            if (!Schema::hasColumn('wa_broadcasts', 'window_sent')) {
                $t->unsignedInteger('window_sent')->default(0)->after('window_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_broadcasts', function (Blueprint $t) {
            if (Schema::hasColumn('wa_broadcasts', 'window_sent')) $t->dropColumn('window_sent');
            if (Schema::hasColumn('wa_broadcasts', 'window_started_at')) $t->dropColumn('window_started_at');
            if (Schema::hasColumn('wa_broadcasts', 'window_minutes')) $t->dropColumn('window_minutes');
        });
    }
};
