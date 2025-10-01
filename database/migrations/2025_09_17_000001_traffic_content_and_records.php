<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('traffic_app_hourly')) {
            Schema::create('traffic_app_hourly', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->dateTime('bucket')->index();
                $t->string('app', 64)->nullable()->index();
                $t->string('host_ip', 64)->nullable()->index();
                $t->unsignedBigInteger('bytes')->default(0);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('traffic_content_map')) {
            Schema::create('traffic_content_map', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name', 64)->index();
                $t->string('cidr', 64);
                $t->boolean('enabled')->default(true)->index();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('traffic_snapshots')) {
            Schema::create('traffic_snapshots', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('group', 32)->index();
                $t->string('key', 128)->index();
                $t->string('period', 8)->index();
                $t->string('png_path');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_snapshots');
        Schema::dropIfExists('traffic_content_map');
        Schema::dropIfExists('traffic_app_hourly');
    }
};
