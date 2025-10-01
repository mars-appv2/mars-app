<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaStaff extends Model
{
    protected $table = 'wa_staff';
    protected $fillable = ['name','phone','role','active'];
}
