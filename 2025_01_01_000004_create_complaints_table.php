<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateComplaintsTable extends Migration
{
    public function up()
    {
	Schema::create('complaints', function (Blueprint $table) {
	    $table->id();
	    $table->string('source')->default('telegram');
	    $table->bigInteger('chat_id')->nullable();
	    $table->string('user')->nullable();
	    $table->text('message');
	    $table->string('status')->default('open');
	    $table->timestamps();
	});
    }


    public function down()
    {
	Schema::dropIfExists('complaints');
    }
}
