<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateAlertRulesTable extends Migration
{
    public function up()
    {
	Schema::create('alert_rules', function (Blueprint $table) {
	    $table->id();
	    $table->string('name');
	    $table->string('type'); // pppoe_off, iface_down, pubip_off, content_off
	    $table->json('params')->nullable();
	    $table->integer('cooldown_minutes')->default(15);
	    $table->boolean('enabled')->default(true);
	    $table->timestamps();
	});
    }


    public function down()
    {
	Schema::dropIfExists('alert_rules');
    }
}
