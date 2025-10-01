<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wa_messages', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('wa_id', 128)->nullable()->index();
            $t->string('from', 64)->index();
            $t->string('to', 64)->nullable();
            $t->text('text')->nullable();
            $t->string('type', 40)->nullable();
            $t->bigInteger('ts')->nullable()->index();
            $t->longText('raw')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('wa_messages');
    }
};
