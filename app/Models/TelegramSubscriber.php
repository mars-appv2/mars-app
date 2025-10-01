<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSubscriber extends Model
{
    protected $fillable = [
        'bot_id','chat_id','user_id','first_name','last_name','username','language_code','is_active','meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function bot()
    {
        return $this->belongsTo(TelegramBot::class,'bot_id');
    }
}
