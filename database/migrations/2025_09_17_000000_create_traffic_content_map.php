<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrafficContentMap extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('traffic_content_map')) {
            Schema::create('traffic_content_map', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('cidr');     // boleh IP tunggal atau CIDR; ping memakai IP tunggal
                $t->boolean('enabled')->default(1);
                $t->timestamps();
            });
        }
    }
    public function down()
    {
        Schema::dropIfExists('traffic_content_map');
    }
}
