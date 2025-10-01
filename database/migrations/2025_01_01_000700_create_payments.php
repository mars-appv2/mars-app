<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration{
  public function up(){ if(!Schema::hasTable('payments')){ Schema::create('payments', function(Blueprint $t){ $t->id(); $t->unsignedBigInteger('invoice_id')->index(); $t->string('gateway')->default('midtrans'); $t->string('order_id')->unique(); $t->unsignedBigInteger('amount'); $t->string('status')->default('pending'); $t->json('payload')->nullable(); $t->timestamps(); }); } }
  public function down(){ Schema::dropIfExists('payments'); }
};
