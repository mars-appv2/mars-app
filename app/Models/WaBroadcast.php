<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaBroadcast extends Model
{
    protected $fillable = [
        'text','rate_per_min','status','total','sent','failed','created_by','next_run_at'
    ];

    protected $casts = [
        'next_run_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(WaBroadcastRecipient::class);
    }
}
