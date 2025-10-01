<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['subscription_id','period_start','period_end','amount','status','due_at','paid_at'];

    public function subscription(){ return $this->belongsTo(Subscription::class); }
}
