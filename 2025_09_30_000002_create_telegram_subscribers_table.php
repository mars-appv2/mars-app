<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTelegramSubscribersTable extends Migration
{
    public function up()
    {
        Schema::create('telegram_subscribers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bot_id')->index();
            $table->string('chat_id')->index(); // simpan chat_id Telegram
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('language_code')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('bot_id')->references('id')->on('telegram_bots')->onDelete('cascade');
            $table->unique(['bot_id','chat_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegram_subscribers');
    }
}
