<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLatencyTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('traffic_latency')) {
            Schema::create('traffic_latency', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('target_ip',64);
                $t->boolean('success');
                $t->unsignedInteger('rtt_ms')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (!Schema::hasTable('traffic_latency_alerts')) {
            Schema::create('traffic_latency_alerts', function (Blueprint $t) {
                $t->id();
                $t->string('target_ip',64)->unique();
                $t->unsignedTinyInteger('last_notified_pct')->default(255);
                $t->timestamps();
            });
        }
    }
    public function down()
    {
        Schema::dropIfExists('traffic_latency_alerts');
        Schema::dropIfExists('traffic_latency');
    }
}
