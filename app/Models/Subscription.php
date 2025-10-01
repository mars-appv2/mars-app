<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['username','plan_id','started_at','ends_at','status'];

    public function plan(){ return $this->belongsTo(Plan::class); }
    public function scopeActive($q){ return $q->where('status','active'); }
}
