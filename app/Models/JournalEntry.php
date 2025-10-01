<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'date','ref','description','created_by','posted_at'
    ];

    protected $dates = ['date','posted_at'];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
