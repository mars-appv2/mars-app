<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('settings')){ Schema::create('settings', function(Blueprint $t){ $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); }); } }
  public function down(){ Schema::dropIfExists('settings'); }
};
