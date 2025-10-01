<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficSamplesTable extends Migration
{
    public function up()
    {
        Schema::create('traffic_samples', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('mikrotik_id')->index();  // id device
            $t->string('target', 191)->index();              // nama interface / pppoe user / ip
            $t->unsignedBigInteger('rx_bps')->default(0);
            $t->unsignedBigInteger('tx_bps')->default(0);
            $t->timestamps();
            $t->index(['mikrotik_id','target','created_at'], 'ts_idx_mtk_target_time');
        });
    }

    public function down()
    {
        Schema::dropIfExists('traffic_samples');
    }
}
