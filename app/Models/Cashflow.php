<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Cashflow extends Model{
  protected $fillable=['type','amount','date','note','invoice_id'];
  protected $casts=['date'=>'date'];
}
