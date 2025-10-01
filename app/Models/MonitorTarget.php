<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MonitorTarget extends Model{
  protected $fillable=['mikrotik_id','target_type','target_key','label','enabled','interval_sec'];
  protected $casts=['enabled'=>'boolean'];
}
