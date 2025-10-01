<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
	    $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
	    $table->string('user_name')->nullable(); // <= TAMBAH
    	    $table->string('user_email')->nullable(); // <= TAMBAH
    	    $table->string('action')->nullable();
    	    $table->string('target')->nullable();
    	    $table->string('status')->nullable();
    	    $table->string('ip')->nullable();
    	    $table->string('route')->nullable();
    	    $table->text('info')->nullable();
    	    $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
