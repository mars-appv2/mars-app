<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOwnerToMikrotiks extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            if (!Schema::hasColumn('mikrotiks', 'owner_id')) {
                $table->foreignId('owner_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('owner_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mikrotiks', function (Blueprint $table) {
            if (Schema::hasColumn('mikrotiks', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }
        });
    }
}
