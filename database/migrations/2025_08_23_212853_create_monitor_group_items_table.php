<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('monitor_group_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('group_id');
            $t->string('iface'); // nama interface (ether1, sfp-sfpplus1, dsb)
            $t->timestamps();
            $t->index(['group_id','iface']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('monitor_group_items');
    }
};
