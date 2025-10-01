<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientWifiRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('client_wifi_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('ssid', 64);
            $table->text('password'); // encrypted
            $table->string('status', 20)->default('queued'); // queued|applied|failed
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('client_wifi_requests');
    }
}
