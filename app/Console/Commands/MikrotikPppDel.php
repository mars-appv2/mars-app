<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Mikrotik;
use App\Services\RouterOSService;

class MikrotikPppDel extends Command {
    protected $signature = 'mikrotik:pppoe-del {id} {name}';
    protected $description = 'Delete PPPoE secret on Mikrotik';
    public function handle() {
        $m = Mikrotik::findOrFail($this->argument('id'));
        $svc = new RouterOSService($m);
        \Log::info('[ROS_CMD] ppp.remove.cli', ['id'=>$m->id,'name'=>$this->argument('name')]);
        $svc->pppRemove($this->argument('name'));
        $this->info('OK');
    }
}
