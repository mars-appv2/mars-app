<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_broadcasts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('text'); // <- kolom pesan
            $table->unsignedInteger('rate_per_min')->default(5);
            $table->enum('status', ['pending','running','paused','done','failed'])->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('wa_broadcast_recipients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wa_broadcast_id');
            $table->string('phone', 32)->index();
            $table->enum('status', ['pending','sent','failed'])->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->foreign('wa_broadcast_id')
                ->references('id')->on('wa_broadcasts')
                ->onDelete('cascade');

            $table->unique(['wa_broadcast_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_broadcast_recipients');
        Schema::dropIfExists('wa_broadcasts');
    }
};
