<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Mikrotik;

class ProvisioningService
{
    /**
     * Tentukan MikroTik target untuk user/plan tertentu.
     * Urutan:
     *  1) subscriptions.mikrotik_id (jika ada kolomnya)
     *  2) plans.mikrotik_id (jika ada kolomnya)
     *  3) env DEFAULT_MIKROTIK_ID
     *  4) MikroTik terakhir (id terbesar)
     * Return: Mikrotik|null
     */
    public function resolveMikrotikForUser(string $username, ?string $plan): ?Mikrotik
    {
        // 1) subscriptions.mikrotik_id
        try {
            if (Schema::hasColumn('subscriptions', 'mikrotik_id')) {
                $mid = DB::table('subscriptions')->where('username',$username)->value('mikrotik_id');
                if ($mid) {
                    $m = Mikrotik::find($mid);
                    if ($m) return $m;
                }
            }
        } catch (\Throwable $e) {}

        // 2) plans.mikrotik_id
        try {
            if ($plan && Schema::hasColumn('plans','mikrotik_id')) {
                $mid = DB::table('plans')->where('name',$plan)->value('mikrotik_id');
                if ($mid) {
                    $m = Mikrotik::find($mid);
                    if ($m) return $m;
                }
            }
        } catch (\Throwable $e) {}

        // 3) DEFAULT_MIKROTIK_ID
        $def = (int) env('DEFAULT_MIKROTIK_ID', 0);
        if ($def > 0) {
            $m = Mikrotik::find($def);
            if ($m) return $m;
        }

        // 4) fallback: MikroTik terakhir
        return Mikrotik::orderByDesc('id')->first();
    }

    /**
     * Tambahkan atau update PPPoE secret di MikroTik.
     * - Jika secret ada → update password/profile
     * - Jika belum ada → add
     * Return array: ['ok'=>bool, 'msg'=>string]
     */
    public function provisionPppoe(Mikrotik $mk, string $username, string $password, ?string $profile): array
    {
        try {
            $client = new \RouterOS\Client([
                'host'     => $mk->host,
                'user'     => $mk->username,
                'pass'     => $mk->password,
                'port'     => $mk->port ?: 8728,
                'timeout'  => 8,
                'attempts' => 1,
            ]);

            // Cek apakah secret sudah ada
            $q = (new \RouterOS\Query('/ppp/secret/print'))
                ->where('name', $username)
                ->where('.proplist', '.id,name,service,profile');
            $res = $client->query($q)->read();

            if (!empty($res)) {
                $id = $res[0]['.id'] ?? null;
                if ($id) {
                    // Update
                    $set = (new \RouterOS\Query('/ppp/secret/set'))
                        ->equal('.id', $id)
                        ->equal('password', $password)
                        ->equal('service', 'pppoe');
                    if ($profile) $set->equal('profile', $profile);
                    $client->query($set)->read();
                    return ['ok'=>true, 'msg'=>"Secret diupdate (id={$id})."];
                }
            }

            // Add baru
            $add = (new \RouterOS\Query('/ppp/secret/add'))
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('service', 'pppoe');
            if ($profile) $add->equal('profile', $profile);
            $client->query($add)->read();

            return ['ok'=>true, 'msg'=>'Secret dibuat.'];
        } catch (\Throwable $e) {
            Log::error('[Provisioning] PPPoE error: '.$e->getMessage());
            return ['ok'=>false, 'msg'=>$e->getMessage()];
        }
    }
}
