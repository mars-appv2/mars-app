<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MikrotikBackup extends Model
{
    protected $fillable = [
        'mikrotik_id','type','filename','size','sha1','meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function mikrotik()
    {
        return $this->belongsTo(\App\Models\Mikrotik::class);
    }
}
