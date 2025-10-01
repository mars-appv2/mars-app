<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(){
        // HANYA buat tabel kalau belum ada
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function(Blueprint $t){
                $t->id();
                $t->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
                $t->date('period_start');
                $t->date('period_end');
                $t->unsignedInteger('amount');
                $t->enum('status',['unpaid','paid','void'])->default('unpaid');
                $t->date('due_at')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(){
        Schema::dropIfExists('invoices');
    }
};
