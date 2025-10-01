<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tickets')) return;

        Schema::table('tickets', function (Blueprint $t) {
            if (!Schema::hasColumn('tickets','code'))        $t->string('code',40)->nullable()->unique()->after('id');
            if (!Schema::hasColumn('tickets','subject'))     $t->string('subject')->nullable()->after('code');
            if (!Schema::hasColumn('tickets','description')) $t->text('description')->nullable()->after('subject');
            if (!Schema::hasColumn('tickets','status'))      $t->string('status')->default('open')->index()->after('description');
            if (!Schema::hasColumn('tickets','priority'))    $t->string('priority')->default('normal')->after('status');
            if (!Schema::hasColumn('tickets','created_by'))  $t->unsignedBigInteger('created_by')->nullable()->index()->after('priority');
        });
    }
    public function down(): void { /* no-op */ }
};
