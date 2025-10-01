<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaBroadcastRecipient extends Model
{
    protected $fillable = ['wa_broadcast_id','phone','status','last_error','sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WaBroadcast::class,'wa_broadcast_id');
    }
}
