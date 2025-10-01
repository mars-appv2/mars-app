<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'user_email', 'action', 'method',
        'target_type', 'target_key', 'status', 'ip', 'route',
        'path', 'message', 'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
