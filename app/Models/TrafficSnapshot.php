<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrafficSnapshot extends Model { protected $table='traffic_snapshots'; protected $fillable=['type','path','meta']; }