<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\MonitorTarget;
use App\Models\Mikrotik;
use App\Models\TrafficRecord;
use App\Models\TrafficCounter;
use App\Services\RouterOSService;

class TrafficCollect extends Command{
  protected $signature='traffic:collect';
  protected $description='Collect RX/TX for targets (interface, pppoe, ip)';
  public function handle(){
    $now=now(); $targets=MonitorTarget::where('enabled',true)->get();
    foreach($targets as $t){
      $dev=Mikrotik::find($t->mikrotik_id); if(!$dev) continue;
      try{
        $svc=new RouterOSService($dev); $scope="{$t->target_type}:{$t->mikrotik_id}:{$t->target_key}"; $rx_bps=0; $tx_bps=0;
        if($t->target_type==='interface'){ $res=$svc->monitorInterface($t->target_key); $rx_bps=(int)($res['rx']/8); $tx_bps=(int)($res['tx']/8); }
        elseif($t->target_type==='pppoe'){ $res=$svc->monitorInterface('pppoe-'.$t->target_key); $rx_bps=(int)($res['rx']/8); $tx_bps=(int)($res['tx']/8); }
        elseif($t->target_type==='ip'){
          $q='mon-'.$t->target_key; $svc->ensureSimpleQueue($q,$t->target_key); $bytes=$svc->getSimpleQueueBytes($q);
          $ctr=TrafficCounter::firstOrCreate(['scope'=>$scope]); $elapsed=$ctr->last_at ? max($now->diffInSeconds($ctr->last_at),1):60;
          $delta_rx=max((int)$bytes['rx_bytes']-(int)$ctr->last_rx,0); $delta_tx=max((int)$bytes['tx_bytes']-(int)$ctr->last_tx,0);
          $rx_bps=(int)round($delta_rx/$elapsed); $tx_bps=(int)round($delta_tx/$elapsed);
          $ctr->update(['last_rx'=>$bytes['rx_bytes'],'last_tx'=>$bytes['tx_bytes'],'last_at'=>$now]);
        }
        TrafficRecord::create(['scope'=>$scope,'rx'=>$rx_bps,'tx'=>$tx_bps,'recorded_at'=>$now]);
      }catch(\Throwable $e){ \Log::error('traffic collect: '.$e->getMessage()); continue; }
    }
    return 0;
  }
}
