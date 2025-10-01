<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMikrotikBackupsTable extends Migration
{
    public function up()
    {
        Schema::create('mikrotik_backups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mikrotik_id');
            $table->string('type', 32); // radius-json | export-rsc | backup-bin
            $table->string('filename', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha1', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['mikrotik_id','type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mikrotik_backups');
    }
}
