<?php
namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadUserGroup extends Model
{
    protected $connection = 'radius_mysql';
    protected $table = 'radusergroup';
    public $timestamps = false;

    protected $fillable = ['username','groupname','priority'];
}
