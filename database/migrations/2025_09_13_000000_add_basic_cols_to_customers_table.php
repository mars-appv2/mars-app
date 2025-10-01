<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) return;

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers','username'))      $table->string('username',120)->nullable()->after('id');
            if (!Schema::hasColumn('customers','name'))          $table->string('name',120)->nullable()->after('username');
            if (!Schema::hasColumn('customers','mikrotik_id'))   $table->unsignedBigInteger('mikrotik_id')->nullable()->after('name');
            if (!Schema::hasColumn('customers','service_type'))  $table->string('service_type',20)->nullable()->after('mikrotik_id');
            if (!Schema::hasColumn('customers','router_profile'))$table->string('router_profile',64)->nullable()->after('service_type');
            if (!Schema::hasColumn('customers','vlan_id'))       $table->unsignedInteger('vlan_id')->nullable()->after('router_profile');
            if (!Schema::hasColumn('customers','ip_address'))    $table->string('ip_address',64)->nullable()->after('vlan_id');
            if (!Schema::hasColumn('customers','plan_id'))       $table->unsignedBigInteger('plan_id')->nullable()->after('ip_address');
            if (!Schema::hasColumn('customers','is_active'))     $table->boolean('is_active')->default(true)->after('plan_id');
            if (!Schema::hasColumn('customers','created_by'))    $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
            if (!Schema::hasColumn('customers','password_plain'))$table->string('password_plain',64)->nullable()->after('created_by');
            if (!Schema::hasColumn('customers','note'))          $table->string('note',255)->nullable()->after('password_plain');

            if (!Schema::hasColumn('customers','created_at') && !Schema::hasColumn('customers','updated_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // aman: tidak perlu drop kolom saat rollback
    }
};
