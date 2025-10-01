<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mikrotik extends Model
{


    protected $fillable = [
        'owner_id','name','host','port','username','password',
        'radius_enabled','radius_secret',
    ];

    public function scopeForUser($q, $user)
    {
        if (!$user) return $q->whereRaw('1=0');
	if ($user->hasRole('admin')) {
            return $q; // admin lihat semua
        }

	return $q->where('owner_id', $user->id);
    }
}
