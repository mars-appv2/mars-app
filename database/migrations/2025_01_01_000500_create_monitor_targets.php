<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('monitor_targets')){ Schema::create('monitor_targets', function(Blueprint $t){ $t->id(); $t->unsignedBigInteger('mikrotik_id')->index(); $t->string('target_type'); $t->string('target_key'); $t->string('label')->nullable(); $t->boolean('enabled')->default(true); $t->unsignedInteger('interval_sec')->default(60); $t->timestamps(); $t->unique(['mikrotik_id','target_type','target_key'],'uniq_target'); }); } }
  public function down(){ Schema::dropIfExists('monitor_targets'); }
};
