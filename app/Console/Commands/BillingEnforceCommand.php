<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingEnforcer;

class BillingEnforceCommand extends Command
{
    protected $signature = 'billing:enforce {--mikrotik_id=} {--user=*}';
    protected $description = 'Enforce billing (isolate overdue users, restore paid users)';

    public function handle(BillingEnforcer $enf)
    {
        $mik = $this->option('mikrotik_id');
        $users = (array)$this->option('user');

        $res = $enf->run($mik ? (int)$mik : null, $users);
        $this->info("Processed {$res->total} subs. Isolated={$res->isolated}, Restored={$res->restored}, RouterUpdates={$res->routerUpdates}");
        return 0;
    }
}
