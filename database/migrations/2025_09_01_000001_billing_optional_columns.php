<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                if (!Schema::hasColumn('subscriptions', 'plan_id'))     $t->unsignedBigInteger('plan_id')->nullable()->after('mikrotik_id');
                if (!Schema::hasColumn('subscriptions', 'plan'))        $t->string('plan')->nullable()->after('plan_id');
                if (!Schema::hasColumn('subscriptions', 'username'))    $t->string('username')->index()->change();
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $t) {
                if (!Schema::hasColumn('invoices', 'period'))    $t->string('period', 7)->nullable()->index(); // YYYY-MM
                if (!Schema::hasColumn('invoices', 'due_date'))  $t->dateTime('due_date')->nullable()->index();
                if (!Schema::hasColumn('invoices', 'status'))    $t->string('status', 20)->default('unpaid')->index();
                if (!Schema::hasColumn('invoices', 'amount'))    $t->bigInteger('amount')->default(0);
                if (!Schema::hasColumn('invoices', 'subscription_id')) $t->unsignedBigInteger('subscription_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $t) {
                if (!Schema::hasColumn('plans', 'price_month')) $t->bigInteger('price_month')->default(0)->after('name');
                if (!Schema::hasColumn('plans', 'mikrotik_id')) $t->unsignedBigInteger('mikrotik_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // biarkan (no-op) agar tidak menghapus kolom yang sudah dipakai
    }
};
