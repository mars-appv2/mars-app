<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateAlertsLogTable extends Migration
{
    public function up()
    {
	Schema::create('alerts_log', function (Blueprint $table) {
	    $table->id();
	    $table->unsignedBigInteger('rule_id');
	    $table->string('status'); // triggered|recovered
	    $table->text('message');
	    $table->json('meta')->nullable();
	    $table->timestamps();
	});
    }


    public function down()
    {
	Schema::dropIfExists('alerts_log');
    }
}
