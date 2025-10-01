<?php
namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadCheck extends Model
{
    protected $table = 'radcheck';
    protected $connection = 'radius';
    public $timestamps = false;

    protected $fillable = ['username','attribute','op','value'];
}
