<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMikrotikIdToPlansTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $t) {
                if (!Schema::hasColumn('plans', 'price')) {
                    $t->unsignedInteger('price')->default(0);
                }
                if (!Schema::hasColumn('plans', 'price_month')) {
                    $t->unsignedInteger('price_month')->default(0);
                }
                if (!Schema::hasColumn('plans', 'mikrotik_id')) {
                    $t->unsignedBigInteger('mikrotik_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $t) {
                if (Schema::hasColumn('plans', 'mikrotik_id')) {
                    $t->dropIndex(['mikrotik_id']);
                    $t->dropColumn('mikrotik_id');
                }
                if (Schema::hasColumn('plans', 'price_month')) {
                    $t->dropColumn('price_month');
                }
                if (Schema::hasColumn('plans', 'price')) {
                    $t->dropColumn('price');
                }
            });
        }
    }
}
