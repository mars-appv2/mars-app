<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrafficSample extends Model
{
    protected $table = 'traffic_samples';
    public $timestamps = true;

    // aman: tidak pakai mass assignment
    protected $guarded = [];
}
