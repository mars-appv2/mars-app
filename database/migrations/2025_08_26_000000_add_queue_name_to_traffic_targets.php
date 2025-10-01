<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // 1) Kalau ada tabel lama dengan nama berbeda, rename dulu
        if (!Schema::hasTable('traffic_targets')) {
            if (Schema::hasTable('targets')) {
                Schema::rename('targets', 'traffic_targets');
            } elseif (Schema::hasTable('monitor_targets')) {
                Schema::rename('monitor_targets', 'traffic_targets');
            }
        }

        // 2) Jika masih belum ada, buat baru lengkap
        if (!Schema::hasTable('traffic_targets')) {
            Schema::create('traffic_targets', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('mikrotik_id')->index();
                $t->string('target_type', 16)->default('interface'); // interface|pppoe|ip
                $t->string('target_key', 191);                       // ether1 / pppoe-name / 1.2.3.4/32
                $t->string('label', 191)->nullable();
                $t->string('queue_name', 128)->nullable();           // md-1.2.3.4 (untuk target IP)
                $t->boolean('enabled')->default(true)->index();
                $t->timestamps();
            });
        } else {
            // 3) Sudah ada: tambahkan kolom yang kurang saja (idempotent)
            Schema::table('traffic_targets', function (Blueprint $t) {
                if (!Schema::hasColumn('traffic_targets','mikrotik_id')) {
                    $t->unsignedBigInteger('mikrotik_id')->index()->after('id');
                }
                if (!Schema::hasColumn('traffic_targets','target_type')) {
                    $t->string('target_type',16)->default('interface')->after('mikrotik_id');
                }
                if (!Schema::hasColumn('traffic_targets','target_key')) {
                    $t->string('target_key',191)->after('target_type');
                }
                if (!Schema::hasColumn('traffic_targets','label')) {
                    $t->string('label',191)->nullable()->after('target_key');
                }
                if (!Schema::hasColumn('traffic_targets','queue_name')) {
                    $t->string('queue_name',128)->nullable()->after('label');
                }
                if (!Schema::hasColumn('traffic_targets','enabled')) {
                    $t->boolean('enabled')->default(true)->after('queue_name')->index();
                }
                if (!Schema::hasColumn('traffic_targets','created_at')) {
                    $t->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        // Tidak drop table. Hanya drop kolom queue_name jika ada.
        if (Schema::hasTable('traffic_targets') && Schema::hasColumn('traffic_targets','queue_name')) {
            Schema::table('traffic_targets', function (Blueprint $t) {
                $t->dropColumn('queue_name');
            });
        }
    }
};
