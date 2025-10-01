<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'code','name','type','parent_id','is_cash','is_active'
    ];

    const TYPE_ASSET    = 1;
    const TYPE_LIAB     = 2;
    const TYPE_EQUITY   = 3;
    const TYPE_REVENUE  = 4;
    const TYPE_EXPENSE  = 5;

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function typeLabel(): string
    {
        return [
            self::TYPE_ASSET   => 'Asset',
            self::TYPE_LIAB    => 'Liability',
            self::TYPE_EQUITY  => 'Equity',
            self::TYPE_REVENUE => 'Revenue',
            self::TYPE_EXPENSE => 'Expense',
        ][$this->type] ?? 'Unknown';
    }
}
