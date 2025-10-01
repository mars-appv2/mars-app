<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrafficAppHourly extends Model { public $timestamps=false; protected $table='traffic_app_hourly'; protected $fillable=['bucket','host_ip','app','bytes']; protected $casts=['bucket'=>'datetime']; }