<?php
namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadReply extends Model
{
    protected $connection = 'radius_mysql';
    protected $table = 'radreply';
    public $timestamps = false;

    protected $fillable = ['username','attribute','op','value'];
}
