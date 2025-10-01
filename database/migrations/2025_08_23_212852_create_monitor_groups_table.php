<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('monitor_groups', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mikrotik_id');
            $t->string('name');
            $t->timestamps();
            $t->unique(['mikrotik_id','name']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('monitor_groups');
    }
};
