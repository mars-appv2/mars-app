<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cust_invoices')) {
            Schema::create('cust_invoices', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('number')->nullable()->index();   // INV code (opsional)
                $table->integer('amount')->default(0);           // pakai integer rupiah biar aman
                $table->date('bill_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->default('unpaid')->index(); // unpaid|paid|lunas|...
                $table->string('method')->nullable();            // cash/transfer/qris (saat pay)
                $table->string('ref_no')->nullable();            // no. referensi
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        } else {
            // Pastikan kolom-kolom kunci ada (idempotent)
            Schema::table('cust_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('cust_invoices','amount'))    $table->integer('amount')->default(0)->after('number');
                if (!Schema::hasColumn('cust_invoices','status'))    $table->string('status')->default('unpaid')->index()->after('due_date');
                if (!Schema::hasColumn('cust_invoices','method'))    $table->string('method')->nullable()->after('status');
                if (!Schema::hasColumn('cust_invoices','ref_no'))    $table->string('ref_no')->nullable()->after('method');
                if (!Schema::hasColumn('cust_invoices','paid_at'))   $table->timestamp('paid_at')->nullable()->after('ref_no');
            });
        }
    }

    public function down(): void
    {
        // Jangan di-drop agar aman
        // Schema::dropIfExists('cust_invoices');
    }
};
