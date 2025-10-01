<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('cashflows')){ Schema::create('cashflows', function(Blueprint $t){ $t->id(); $t->enum('type',['in','out']); $t->unsignedBigInteger('amount'); $t->date('date'); $t->string('note')->nullable(); $t->unsignedBigInteger('invoice_id')->nullable(); $t->timestamps(); }); } }
  public function down(){ Schema::dropIfExists('cashflows'); }
};
