<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('traffic_app_hourly')) {
            Schema::create('traffic_app_hourly', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->dateTime('bucket')->index();         // jam (YYYY-mm-dd HH:00:00)
                $t->string('app', 64)->nullable()->index();      // google/meta/tiktok/...
                $t->string('host_ip', 64)->nullable()->index();  // IP PPPoE / host
                $t->unsignedBigInteger('bytes')->default(0);     // akumulasi per jam
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('traffic_snapshots')) {
            Schema::create('traffic_snapshots', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('group', 32)->index();        // 'interfaces' | 'pppoe' | 'apps'
                $t->string('key', 128)->index();         // if_1@router | 36.XX.XX.XX | google
                $t->string('period', 8)->index();        // day|week|month|year
                $t->string('png_path');                  // path PNG yang disimpan
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_snapshots');
        Schema::dropIfExists('traffic_app_hourly');
    }
};
