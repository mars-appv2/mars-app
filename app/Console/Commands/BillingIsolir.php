<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Invoice; use App\Models\Mikrotik; use App\Services\RouterOSService;

class BillingIsolir extends Command{
  protected $signature='billing:isolir';
  protected $description='Auto disable/enable PPPoE sesuai status invoice & due date';
  public function handle(){
    foreach(Invoice::all() as $inv){
      if(!$inv->pppoe_username || !$inv->mikrotik_id) continue;
      $m=Mikrotik::find($inv->mikrotik_id); if(!$m) continue;
      try{
        $svc=new RouterOSService($m);
        if($inv->status==='unpaid' && $inv->due_date && now()->greaterThan($inv->due_date)){ $svc->pppSet($inv->pppoe_username,['disabled'=>'yes']); }
        if($inv->status==='paid'){ $svc->pppSet($inv->pppoe_username,['disabled'=>'no']); }
      }catch(\Throwable $e){ \Log::error('isolir: '.$e->getMessage()); }
    }
    return 0;
  }
}
