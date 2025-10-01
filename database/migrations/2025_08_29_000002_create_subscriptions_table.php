<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(){
        Schema::create('subscriptions', function(Blueprint $t){
            $t->id();
            $t->string('username');          // username RADIUS
            $t->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $t->date('started_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->enum('status',['active','suspended','ended'])->default('active');
            $t->timestamps();
            $t->index('username');
        });
    }
    public function down(){ Schema::dropIfExists('subscriptions'); }
};
