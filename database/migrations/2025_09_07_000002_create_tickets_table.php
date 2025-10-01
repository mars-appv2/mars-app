<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->enum('type', ['psb','complain']);
            $t->string('username')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('customer_phone', 32)->nullable();
            $t->string('address', 255)->nullable();
            $t->text('description')->nullable();
            $t->enum('status', ['open','assigned','closed'])->default('open');
            $t->unsignedBigInteger('assigned_to')->nullable(); // wa_staff.id
            $t->unsignedBigInteger('created_by')->nullable();  // wa_staff.id
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
