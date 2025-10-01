<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Mikrotik;

class BillingSyncSubs extends Command
{
    protected $signature   = 'billing:sync-subs {--mikrotik_id=} {--dry}';
    protected $description = 'Sync subscriptions from RADIUS (radcheck/radusergroup) to subscriptions table';

    public function handle()
    {
        $mikId = $this->option('mikrotik_id') ? (int) $this->option('mikrotik_id') : null;
        $dry   = (bool) $this->option('dry');

        // 1) Ambil semua username (punya password) dari RADIUS
        $usersQ = DB::connection('radius')->table('radcheck')
            ->select('username')
            ->where('attribute', 'Cleartext-Password')
            ->distinct();

        // (opsional) filter per router melalui radacct (NAS IP)
        if ($mikId) {
            $host = Mikrotik::query()->where('id', $mikId)->value('host');
            if ($host) {
                $usersQ->whereIn('username', function ($q) use ($host) {
                    $q->from('radacct')
                        ->select('username')
                        ->where('nasipaddress', $host)
                        ->distinct();
                });
            }
        }

        $usernames = $usersQ->pluck('username')->all();
        if (empty($usernames)) {
            $this->info('No users found from RADIUS.');
            return self::SUCCESS;
        }

        // 2) Map username -> group/plan name (radusergroup)
        $groupMap = DB::connection('radius')->table('radusergroup')
            ->whereIn('username', $usernames)
            ->pluck('groupname', 'username');

        // 3) Map username -> mikrotik_id via radacct (NAS-IP → Mikrotik::host)
        $hostToId = Mikrotik::query()->pluck('id', 'host'); // host => id
        $mikByUser = [];
        $lastSessions = DB::connection('radius')->table('radacct')
            ->select('username', 'nasipaddress', DB::raw('MAX(acctstarttime) as last'))
            ->whereIn('username', $usernames)
            ->groupBy('username', 'nasipaddress')
            ->get();

        foreach ($lastSessions as $s) {
            $id = $hostToId[$s->nasipaddress] ?? null;
            if ($id) $mikByUser[$s->username] = $id;
        }

        // 4) Index plan => plan_id per mikrotik
        $plans = DB::table('plans')->select('id', 'name', 'mikrotik_id')->get();
        $planIndex = []; // key: "<mikrotik_id>|<lower(name)>"
        foreach ($plans as $p) {
            $planIndex[ ($p->mikrotik_id ?? 0) . '|' . mb_strtolower($p->name) ] = $p->id;
        }

        // 5) Build rows & upsert
        $rows  = [];
        $count = 0;
        $now   = now();

        foreach ($usernames as $u) {
            $mid      = $mikByUser[$u] ?? $mikId ?? null;         // fallback: opsi CLI
            $planName = $groupMap[$u] ?? null;
            $planId   = null;

            if ($planName) {
                $key = ($mid ?? 0) . '|' . mb_strtolower($planName);
                $planId = $planIndex[$key] ?? null;
            }

            $rows[] = [
                'username'    => $u,
                'mikrotik_id' => $mid,
                'plan_id'     => $planId,       // boleh null
                'status'      => 'active',
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            if (count($rows) >= 500) {
                if (!$dry) {
                    DB::table('subscriptions')->upsert(
                        $rows,
                        ['username'],                          // unique by username
                        ['mikrotik_id', 'plan_id', 'status', 'updated_at']
                    );
                }
                $count += count($rows);
                $rows = [];
            }
        }

        if ($rows) {
            if (!$dry) {
                DB::table('subscriptions')->upsert(
                    $rows,
                    ['username'],
                    ['mikrotik_id', 'plan_id', 'status', 'updated_at']
                );
            }
            $count += count($rows);
        }

        $this->info("Synced {$count} subscriptions.");
        return self::SUCCESS;
    }
}
