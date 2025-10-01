<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Mikrotik;
use App\Services\RouterOSService;

class MikrotikPppAdd extends Command {
    protected $signature = 'mikrotik:pppoe-add {id} {name} {pass} {profile=default}';
    protected $description = 'Add PPPoE secret on Mikrotik';
    public function handle() {
        $m = Mikrotik::findOrFail($this->argument('id'));
        $svc = new RouterOSService($m);
        \Log::info('[ROS_CMD] ppp.add.cli', ['id'=>$m->id,'name'=>$this->argument('name'),'profile'=>$this->argument('profile')]);
        $svc->pppAdd($this->argument('name'), $this->argument('pass'), $this->argument('profile'));
        $this->info('OK');
    }
}
