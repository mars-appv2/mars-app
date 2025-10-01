<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Mikrotik;
use App\Services\RouterOSService;
use Illuminate\Support\Facades\Log;

class BillingEnforce extends Command
{
    protected $signature = 'billing:enforce {--limit=2000}';
    protected $description = 'Isolir pelanggan dengan invoice overdue (status=unpaid & due_date < now)';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');

        $rows = DB::table('invoices')
            ->join('subscriptions', 'subscriptions.id', '=', 'invoices.subscription_id')
            ->select(
                'invoices.id as iid',
                'subscriptions.username as username',
                'subscriptions.mikrotik_id as mikrotik_id',
                'invoices.due_date as due_date',
                'invoices.status as inv_status',
                'invoices.created_at as created_at'
            )
            ->where('invoices.status', 'unpaid')
            ->whereNotNull('invoices.due_date')
            ->where('invoices.due_date', '<', now())
            ->limit($limit)
            ->get();

        $isolated = 0;

        foreach ($rows as $r) {
            if (!$r->mikrotik_id || !$r->username) continue;

            $mk = Mikrotik::find($r->mikrotik_id);
            if (!$mk) continue;

            try {
                $svc = new RouterOSService($mk);
                // Mekanisme isolir: disable PPP secret
                $svc->pppSet($r->username, ['disabled' => 'yes']);
                $isolated++;
            } catch (\Throwable $e) {
                Log::warning('[BILLING ENFORCE] fail', [
                    'iid' => $r->iid,
                    'mk'  => $r->mikrotik_id,
                    'user'=> $r->username,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Isolated {$isolated} users.");
        return self::SUCCESS;
    }
}
