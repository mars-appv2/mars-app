<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mikrotik;
use App\Services\MikrotikBackupService;

class MikrotikBackupRun extends Command
{
    protected $signature = 'mikrotik:backup {--mikrotik_id=} {--all}';
    protected $description = 'Run backup for one or all Mikrotik devices';

    public function handle()
    {
        $service = app(MikrotikBackupService::class);

        if ($this->option('all')) {
            $list = Mikrotik::orderBy('id')->get();
            $n=0;
            foreach ($list as $m) {
                $this->info("Backing up #{$m->id} {$m->name} ({$m->host})");
                $service->runForDevice($m);
                $n++;
            }
            $this->info("Done. Devices processed: {$n}");
            return 0;
        }

        $id = (int) $this->option('mikrotik_id');
        if ($id <= 0) {
            $this->error('Provide --mikrotik_id=ID or use --all');
            return 1;
        }

        $m = Mikrotik::find($id);
        if (!$m) { $this->error('Mikrotik not found'); return 2; }

        $service->runForDevice($m);
        $this->info('Done.');
        return 0;
    }
}
