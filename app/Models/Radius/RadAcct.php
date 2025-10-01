<?php
namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadAcct extends Model
{
    protected $connection = 'radius_mysql';
    protected $table = 'radacct';
    public $timestamps = false;

    protected $primaryKey = 'radacctid';
}
