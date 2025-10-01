<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(){
        Schema::create('plans', function(Blueprint $t){
            $t->id();
            $t->string('name');
            $t->string('ppp_profile')->nullable();   // nama profile PPPoE di MikroTik (opsional)
            $t->string('rate')->nullable();          // mis. "10M/2M" untuk info
            $t->unsignedInteger('price_month');      // harga per bulan (dalam rupiah)
            $t->string('groupname')->nullable();     // grup RADIUS opsional
            $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('plans'); }
};
