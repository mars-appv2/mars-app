<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('mikrotiks')){ Schema::create('mikrotiks', function(Blueprint $t){ $t->id(); $t->string('name'); $t->string('host'); $t->unsignedInteger('port')->default(8728); $t->string('username'); $t->string('password'); $t->timestamps(); }); } }
  public function down(){ Schema::dropIfExists('mikrotiks'); }
};
