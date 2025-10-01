<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrafficCounter extends Model{
  protected $fillable=['scope','last_rx','last_tx','last_at'];
  protected $casts=['last_at'=>'datetime'];
}
