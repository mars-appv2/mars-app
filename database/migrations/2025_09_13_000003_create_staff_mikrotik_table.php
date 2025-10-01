<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('staff_mikrotik')) {
            Schema::create('staff_mikrotik', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedBigInteger('mikrotik_id')->index();
                $t->timestamps();
                $t->unique(['user_id','mikrotik_id']);
            });
        }
    }
    public function down(): void { /* no-op */ }
};
