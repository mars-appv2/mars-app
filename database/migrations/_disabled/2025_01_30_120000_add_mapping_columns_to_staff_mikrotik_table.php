<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMappingColumnsToStaffMikrotikTable extends Migration
{
    public function up()
    {
        Schema::table('staff_mikrotik', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_mikrotik', 'user_id')) {
                $table->unsignedBigInteger('user_id')->index()->after('id');
            }
            if (!Schema::hasColumn('staff_mikrotik', 'mikrotik_id')) {
                $table->unsignedBigInteger('mikrotik_id')->index()->after('user_id');
            }
            // unique pair (dibungkus try biar idempotent)
            try {
                $table->unique(['user_id','mikrotik_id'], 'staff_mikrotik_user_mikrotik_unique');
            } catch (\Throwable $e) {}
        });
    }

    public function down()
    {
        Schema::table('staff_mikrotik', function (Blueprint $table) {
            try { $table->dropUnique('staff_mikrotik_user_mikrotik_unique'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('staff_mikrotik','mikrotik_id')) $table->dropColumn('mikrotik_id');
            if (Schema::hasColumn('staff_mikrotik','user_id'))     $table->dropColumn('user_id');
        });
    }
}
