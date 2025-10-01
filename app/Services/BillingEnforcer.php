<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforce billing to Mikrotik/RADIUS:
 * - Overdue => profile "Isolir"
 * - Not overdue or paid => restore plan profile
 */
class BillingEnforcer
{
    /** jalankan; return object summary */
    public function run(?int $filterMikId = null, array $onlyUsernames = []): object
    {
        $now = now();
        $disconnectOnChange = filter_var(env('BILLING_DISCONNECT_ON_CHANGE', true), FILTER_VALIDATE_BOOLEAN);

        // index Mikrotik
        $mkRows = DB::table('mikrotiks')->select('id','name','host','username','password','port')->get();
        $mkById = [];
        foreach ($mkRows as $m) { $mkById[(int)$m->id] = $m; }

        // ambil subscriptions aktif
        $subsQ = DB::table('subscriptions as s')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->select([
                's.id','s.username','s.mikrotik_id','s.status',
                DB::raw('COALESCE(p.name, s.plan_name, "default") as plan_name')
            ])
            ->where('s.status','active');

        if ($filterMikId) $subsQ->where('s.mikrotik_id', (int)$filterMikId);
        if (!empty($onlyUsernames)) $subsQ->whereIn('s.username', $onlyUsernames);

        $subs = $subsQ->get();

        $rad = DB::connection('radius');

        $cntIsolir = 0; $cntRestore = 0; $cntRouterUpdates = 0;

        foreach ($subs as $s) {
            $username = (string)$s->username;
            if ($username === '') continue;

            // overdue? ada invoice UNPAID & due_date < sekarang
            $overdue = DB::table('invoices')
                ->where('subscription_id', $s->id)
                ->where('status', 'unpaid')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $now->toDateString())
                ->exists();

            if ($overdue) {
                // 1) set group Isolir di RADIUS
                $rad->table('radusergroup')->updateOrInsert(
                    ['username' => $username],
                    ['groupname' => 'Isolir', 'priority' => 1]
                );

                // 2) set secret profile=Isolir di Mikrotik (jika ada device)
                $mk = $this->resolveMik($s, $mkById, $rad);
                if ($mk) {
                    if ($this->setSecretProfile($mk, $username, 'Isolir', $disconnectOnChange)) {
                        $cntRouterUpdates++;
                    }
                }
                $cntIsolir++;
            } else {
                // 1) pulihkan group ke plan_name (jika kosong -> "default")
                $planName = trim($s->plan_name ?? '') ?: 'default';
                $rad->table('radusergroup')->updateOrInsert(
                    ['username' => $username],
                    ['groupname' => $planName, 'priority' => 1]
                );

                // 2) set secret profile=plan ke Mikrotik (jika ada)
                $mk = $this->resolveMik($s, $mkById, $rad);
                if ($mk) {
                    if ($this->setSecretProfile($mk, $username, $planName, $disconnectOnChange)) {
                        $cntRouterUpdates++;
                    }
                }
                $cntRestore++;
            }
        }

        return (object)[
            'isolated' => $cntIsolir,
            'restored' => $cntRestore,
            'routerUpdates' => $cntRouterUpdates,
            'total' => $subs->count(),
        ];
    }

    /** tentukan mikrotik: dari subscriptions.mikrotik_id; jika kosong, pakai NAS terakhir di radacct */
    private function resolveMik($sub, array $mkById, $radConn)
    {
        $mikId = (int)($sub->mikrotik_id ?? 0);
        if ($mikId && isset($mkById[$mikId])) return $mkById[$mikId];

        // fallback: cari NAS terakhir dari radacct
        $nas = $radConn->table('radacct')
            ->where('username', $sub->username)
            ->orderBy('acctstarttime')
            ->value('nasipaddress');
        if (!$nas) return null;

        foreach ($mkById as $m) {
            if (trim($m->host) === trim($nas)) return $m;
        }
        return null;
    }

    /** set /ppp/secret profile, optional disconnect active */
    private function setSecretProfile($mk, string $username, string $profile, bool $disconnect): bool
    {
        try {
            if (!class_exists(\RouterOS\Client::class)) {
                Log::warning('RouterOS lib missing; skip secret set'); return false;
            }
            $cli = new \RouterOS\Client([
                'host' => $mk->host,
                'user' => $mk->username,
                'pass' => $mk->password,
                'port' => $mk->port ?: 8728,
                'timeout' => 8,
                'attempts' => 1,
            ]);

            // ambil .id secret
            $q = (new \RouterOS\Query('/ppp/secret/print'))->where('name', $username);
            $res = $cli->query($q)->read();
            if (empty($res) || empty($res[0]['.id'])) return false;

            $id = $res[0]['.id'];
            // set profile
            $set = (new \RouterOS\Query('/ppp/secret/set'))
                ->equal('.id', $id)->equal('profile', $profile);
            $cli->query($set)->read();

            if ($disconnect) {
                // kalau aktif, putuskan supaya re-auth dengan profil baru
                $qa = (new \RouterOS\Query('/ppp/active/print'))->where('name', $username);
                $ra = $cli->query($qa)->read();
                if (!empty($ra) && !empty($ra[0]['.id'])) {
                    $rm = (new \RouterOS\Query('/ppp/active/remove'))
                        ->equal('.id', $ra[0]['.id']);
                    $cli->query($rm)->read();
                }
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('Set profile fail for '.$username.' @'.$mk->host.' : '.$e->getMessage());
            return false;
        }
    }
}
