<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('traffic_records')){ Schema::create('traffic_records', function(Blueprint $t){ $t->id(); $t->string('scope')->index(); $t->unsignedBigInteger('rx')->default(0); $t->unsignedBigInteger('tx')->default(0); $t->timestamp('recorded_at')->index(); }); } }
  public function down(){ Schema::dropIfExists('traffic_records'); }
};
