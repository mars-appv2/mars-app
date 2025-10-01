<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_staff', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('phone', 32)->unique(); // 62812xxxx
            $t->enum('role', ['noc','teknisi','staff'])->default('staff');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('wa_staff');
    }
};
