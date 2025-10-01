<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'code','type','username','customer_name','customer_phone',
        'address','description','status','assigned_to','created_by'
    ];
}
