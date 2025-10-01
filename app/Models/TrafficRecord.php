<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrafficRecord extends Model{
  public $timestamps=false;
  protected $fillable=['scope','rx','tx','recorded_at'];
  protected $casts=['recorded_at'=>'datetime'];
}
