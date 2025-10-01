<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Mikrotik;
use RouterOS\Client;
use RouterOS\Query;

/**
 * Sinkron PPP ↔ RADIUS ↔ Billing (PHP 7.4 compatible).
 */
class RadiusBillingSync
{
    /** Sinkron user ke RADIUS & Subscriptions; optional invoice awal; enforce active/inactive. */
    public static function syncUserToRadiusAndBilling(
        string $username,
        ?string $password,
        ?string $profile,
        int $mikrotikId,
        bool $createInvoice = false,
        ?bool $disabled = null
    ): void {
        $rad = DB::connection('radius');

        // Password
        if ($password !== null && $password !== '') {
            $row = $rad->table('radcheck')
                ->where('username',$username)->where('attribute','Cleartext-Password')->first();
            if ($row) {
                $rad->table('radcheck')->where('id',$row->id)->update(['op'=>':=','value'=>$password]);
            } else {
                $rad->table('radcheck')->insert([
                    'username'=>$username,'attribute'=>'Cleartext-Password','op'=>':=','value'=>$password
                ]);
            }
        }

        // Plan/group
        if ($profile !== null && $profile !== '') {
            $rad->table('radusergroup')->updateOrInsert(
                ['username'=>$username], ['groupname'=>$profile,'priority'=>1]
            );
            self::syncProfileToPlan($profile, $mikrotikId);
        }

        // Tentukan disabled
        if ($disabled === null) {
            $hasReject = $rad->table('radcheck')->where('username',$username)
                ->where('attribute','Auth-Type')->where('op',':=')->where('value','Reject')->exists();
            $disabled = $hasReject;
        }
        if ($disabled === null) {
            $disabled = self::detectSecretDisabledOnRouter($mikrotikId, $username);
        }

        // Enforce di RADIUS
        if ($disabled) {
            $row = $rad->table('radcheck')->where([
                ['username','=',$username], ['attribute','=','Auth-Type'],
            ])->first();
            if ($row) {
                $rad->table('radcheck')->where('id',$row->id)->update(['op'=>':=','value'=>'Reject']);
            } else {
                $rad->table('radcheck')->insert([
                    'username'=>$username,'attribute'=>'Auth-Type','op'=>':=','value'=>'Reject'
                ]);
            }
        } else {
            $rad->table('radcheck')->where([
                ['username','=',$username], ['attribute','=','Auth-Type'], ['op',':='], ['value','=','Reject'],
            ])->delete();
        }

        // Subscriptions
        if (Schema::hasTable('subscriptions')) {
            $now = now();

            $planId = null;
            if ($profile !== null && $profile !== '') {
                $planQ = DB::table('plans')->where('name',$profile);
                if (Schema::hasColumn('plans','mikrotik_id')) {
                    $planQ->where(function($q) use($mikrotikId){
                        $q->where('mikrotik_id',$mikrotikId)->orWhereNull('mikrotik_id');
                    });
                }
                $planId = (int) $planQ->value('id') ?: null;
            }

            $unique = ['username'=>$username];
            if (Schema::hasColumn('subscriptions','mikrotik_id')) $unique['mikrotik_id'] = $mikrotikId;

            $subId = DB::table('subscriptions')->where($unique)->value('id');

            $data = ['status'=>($disabled ? 'inactive':'active'), 'updated_at'=>$now];
            if ($planId) $data['plan_id'] = $planId;

            if ($subId) {
                DB::table('subscriptions')->where('id',$subId)->update($data);
            } else {
                $data = array_merge($unique, $data, ['created_at'=>$now]);
                $subId = DB::table('subscriptions')->insertGetId($data);
            }

            if ($createInvoice && !$disabled) {
                self::maybeGenerateFirstInvoice((int)$subId);
            }
        }
    }

    /** Pastikan profile ada sebagai plan lokal (harga default 0). */
    public static function syncProfileToPlan(string $profile, int $mikrotikId): void
    {
        $where = ['name'=>$profile];
        if (Schema::hasColumn('plans','mikrotik_id')) $where['mikrotik_id'] = $mikrotikId;

        $exists = DB::table('plans')->where($where)->first();
        $data   = ['updated_at'=>now()];
        if (Schema::hasColumn('plans','price') && empty($exists))       $data['price'] = 0;
        if (Schema::hasColumn('plans','price_month') && empty($exists)) $data['price_month'] = null;

        if ($exists) {
            DB::table('plans')->where('id',$exists->id)->update($data);
        } else {
            $data = array_merge($where, $data, ['created_at'=>now()]);
            DB::table('plans')->insert($data);
        }
    }

    /** Buat/ubah PPP secret di Mikrotik (add jika belum ada). */
    public static function ensureRouterSecret(
        int $mikrotikId,
        string $username,
        ?string $password,
        ?string $profile,
        ?bool $disabled = null
    ): void {
        try {
            $m = Mikrotik::findOrFail($mikrotikId);
            $c = new Client([
                'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                'port'=>$m->port ?: 8728,'timeout'=>10,'attempts'=>1
            ]);

            $res = $c->query(
                (new Query('/ppp/secret/print'))->where('name',$username)->equal('.proplist','.id')
            )->read();

            if (empty($res)) {
                $q = (new Query('/ppp/secret/add'))->equal('name',$username);
                if ($password !== null && $password !== '') $q->equal('password',$password);
                if ($profile  !== null && $profile  !== '') $q->equal('profile',$profile);
                if ($disabled !== null)                     $q->equal('disabled', $disabled ? 'yes' : 'no');
                $c->query($q)->read();
            } else {
                $set = (new Query('/ppp/secret/set'))->equal('.id',$res[0]['.id']);
                if ($password !== null && $password !== '') $set->equal('password',$password);
                if ($profile  !== null && $profile  !== '') $set->equal('profile',$profile);
                if ($disabled !== null)                     $set->equal('disabled', $disabled ? 'yes' : 'no');
                $c->query($set)->read();
            }
        } catch (\Throwable $e) {
            // biarkan senyap—UI tetap lanjut
        }
    }

    /** Push perubahan cepat ke router & optional disconnect sesi aktif. */
    public static function pushToRouter(
        int $mikrotikId,
        string $username,
        ?string $password,
        ?string $profile,
        ?bool $disabled,
        bool $disconnect = false
    ): void {
        try {
            $m = Mikrotik::findOrFail($mikrotikId);
            $c = new Client([
                'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                'port'=>$m->port ?: 8728,'timeout'=>8,'attempts'=>1,
            ]);

            $row = $c->query(
                (new Query('/ppp/secret/print'))->where('name',$username)->equal('.proplist','.id')
            )->read();
            if (empty($row)) return;
            $id = $row[0]['.id'];

            $set = (new Query('/ppp/secret/set'))->equal('.id',$id);
            if ($password !== null && $password !== '') $set->equal('password',$password);
            if ($profile  !== null && $profile  !== '') $set->equal('profile',$profile);
            if ($disabled !== null)                     $set->equal('disabled', $disabled ? 'yes' : 'no');
            $c->query($set)->read();

            if ($disconnect) {
                $act = $c->query(
                    (new Query('/ppp/active/print'))->where('name',$username)->equal('.proplist','.id')
                )->read();
                if (!empty($act)) {
                    $c->query((new Query('/ppp/active/remove'))->equal('.id',$act[0]['.id']))->read();
                }
            }
        } catch (\Throwable $e) {}
    }

    /** Nomor invoice berikutnya untuk periode Y-m (INVYYYYMM-XXXX). */
    public static function nextInvoiceNumber(string $period): string
    {
        $last = DB::table('invoices')->where('period',$period)->orderBy('id','desc')->value('number');
        $seq  = 1;
        if ($last && preg_match('/-(\d{4})$/',$last,$m)) $seq = ((int)$m[1]) + 1;
        return sprintf('INV%s-%04d', str_replace('-','',$period), $seq);
    }

    /** Generate invoice awal jika belum ada & harga > 0. */
    public static function maybeGenerateFirstInvoice(int $subscriptionId): void
    {
        if (!Schema::hasTable('invoices')) return;

        $row = DB::table('subscriptions as s')
            ->leftJoin('plans as p','p.id','=','s.plan_id')
            ->where('s.id',$subscriptionId)
            ->select('s.id','s.username','s.mikrotik_id',
                DB::raw('COALESCE(p.price_month, p.price, 0) as base_price'))
            ->first();
        if (!$row) return;

        $amount = (int)($row->base_price ?? 0);
        if ($amount <= 0) return;

        $period  = now()->format('Y-m');
        $exists  = DB::table('invoices')->where('subscription_id',$subscriptionId)->where('period',$period)->exists();
        if ($exists) return;

        $number = self::nextInvoiceNumber($period);
        $now    = now();
        $due    = $now->copy()->addDays((int) env('BILLING_DUE_IN_DAYS', 10));

        DB::table('invoices')->insert([
            'subscription_id' => $subscriptionId,
            'mikrotik_id'     => Schema::hasColumn('invoices','mikrotik_id') ? ($row->mikrotik_id ?? null) : null,
            'number'          => $number,
            'customer_name'   => $row->username ?? '',
            'amount'          => $amount,
            'total'           => $amount,
            'status'          => 'unpaid',
            'period'          => $period,
            'due_date'        => $due->format('Y-m-d'),
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /** Deteksi secret disabled pada router. */
    private static function detectSecretDisabledOnRouter(int $mikrotikId, string $username): ?bool
    {
        try {
            $m = Mikrotik::find($mikrotikId); if (!$m) return null;
            $c = new Client([
                'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                'port'=>$m->port ?: 8728,'timeout'=>5,'attempts'=>1,
            ]);
            $row = $c->query(
                (new Query('/ppp/secret/print'))->where('name',$username)->equal('.proplist','disabled')
            )->read();
            if (empty($row)) return null;
            $flag = strtolower((string)($row[0]['disabled'] ?? 'no'));
            return in_array($flag, ['yes','true','on'], true);
        } catch (\Throwable $e) { return null; }
    }

    /** Cari mikrotik_id berdasar username (subs → radacct). */
    public static function resolveMikIdByUsername(string $username): ?int
    {
        try {
            if (Schema::hasTable('subscriptions')) {
                $mik = DB::table('subscriptions')->where('username',$username)
                    ->orderBy('id','desc')->value('mikrotik_id');
                if (!empty($mik)) return (int)$mik;
            }
            $nas = DB::connection('radius')->table('radacct')
                ->where('username',$username)->orderBy('acctstarttime')->value('nasipaddress');
            if ($nas) {
                $mik = DB::table('mikrotiks')->where('host',$nas)->value('id');
                if (!empty($mik)) return (int)$mik;
            }
        } catch (\Throwable $e) {}
        return null;
    }
}
