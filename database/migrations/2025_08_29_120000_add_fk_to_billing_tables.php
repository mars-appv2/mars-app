<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFkToBillingTables extends Migration
{
    public function up(): void
    {
        // subscriptions.mikrotik_id
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                if (!Schema::hasColumn('subscriptions', 'mikrotik_id')) {
                    $t->unsignedBigInteger('mikrotik_id')->nullable()->after('id');
                    $t->index('mikrotik_id');
                }
            });
        }

        // invoices.subscription_id
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $t) {
                if (!Schema::hasColumn('invoices', 'subscription_id')) {
                    $t->unsignedBigInteger('subscription_id')->nullable()->after('id');
                    $t->index('subscription_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'mikrotik_id')) {
            Schema::table('subscriptions', function (Blueprint $t) {
                $t->dropIndex(['mikrotik_id']); // subscriptions_mikrotik_id_index
                $t->dropColumn('mikrotik_id');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'subscription_id')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->dropIndex(['subscription_id']); // invoices_subscription_id_index
                $t->dropColumn('subscription_id');
            });
        }
    }
}
