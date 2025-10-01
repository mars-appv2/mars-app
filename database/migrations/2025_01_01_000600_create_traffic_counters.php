<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('traffic_counters')){ Schema::create('traffic_counters', function(Blueprint $t){ $t->id(); $t->string('scope')->unique(); $t->unsignedBigInteger('last_rx')->default(0); $t->unsignedBigInteger('last_tx')->default(0); $t->timestamp('last_at')->nullable(); $t->timestamps(); }); } }
  public function down(){ Schema::dropIfExists('traffic_counters'); }
};
