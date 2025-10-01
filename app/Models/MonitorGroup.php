<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MonitorGroup extends Model {
    protected $fillable = ['mikrotik_id','name'];
    public function items(){ return $this->hasMany(MonitorGroupItem::class,'group_id'); }
}
