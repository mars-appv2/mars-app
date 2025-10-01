<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cust_invoices')) {
            Schema::create('cust_invoices', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('customer_id')->index();
                $t->string('number')->nullable()->index();
                $t->integer('amount')->default(0);
                $t->date('bill_date')->nullable();
                $t->date('due_date')->nullable();
                $t->string('status')->default('unpaid')->index();
                $t->string('method')->nullable();
                $t->string('ref_no')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('cust_payments')) {
            Schema::create('cust_payments', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('invoice_id')->index();
                $t->integer('amount')->default(0);
                $t->string('method')->nullable();
                $t->string('ref_no')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
            });
        }
    }
    public function down(): void { /* no-op */ }
};
