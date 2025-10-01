<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficContentTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('traffic_content_map')) {
            Schema::create('traffic_content_map', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->string('cidr', 64);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
                $table->index(['name']);
                $table->index(['cidr']);
            });
        }

        if (!Schema::hasTable('traffic_app_hourly')) {
            Schema::create('traffic_app_hourly', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->dateTime('bucket')->index();
                $table->string('app', 64)->nullable();
                $table->string('host_ip', 45)->nullable();
                $table->unsignedBigInteger('bytes')->default(0);
                $table->timestamps();
                $table->index(['bucket','app']);
                $table->index(['bucket','host_ip']);
            });
        }

        if (!Schema::hasTable('traffic_snapshots')) {
            Schema::create('traffic_snapshots', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('group', 32);
                $table->string('key', 128);
                $table->string('period', 16);
                $table->string('png_path', 255);
                $table->timestamps();
                $table->index(['group','key','period']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('traffic_snapshots');
        Schema::dropIfExists('traffic_app_hourly');
        Schema::dropIfExists('traffic_content_map');
    }
}
