<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TelegramBot extends Model
{
    protected $fillable = [
        'name','username','token','company_id','created_by','is_active','settings'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    // Mutator: simpan token terenkripsi
    public function setTokenAttribute($value)
    {
        if ($value === null) {
            $this->attributes['token'] = null;
            return;
        }
        $this->attributes['token'] = Crypt::encryptString($value);
    }

    // Accessor: decrypt token saat dibutuhkan
    public function getTokenAttribute($value)
    {
        if ($value === null) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // fallback: jika tidak bisa decrypt (kadang token sudah plain)
            return $value;
        }
    }

    public function subscribers()
    {
        return $this->hasMany(TelegramSubscriber::class, 'bot_id');
    }
}
