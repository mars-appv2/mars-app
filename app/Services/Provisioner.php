<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Provisioner
{
    public function __construct(
        protected RadiusService $radius,
        protected MikrotikService $mt
    ) {}

    public function activate(object $customer): void
    {
        // Ambil info plan (rate limit, group, profile)
        $plan = $customer->plan_id && Schema::hasTable('plans')
            ? DB::table('plans')->where('id',$customer->plan_id)->first()
            : null;

        $rate   = $plan->rate_limit    ?? null;
        $group  = $plan->radius_group  ?? null;
        $profile= $customer->router_profile ?: ($plan->router_profile ?? null);
        $svc    = $customer->service_type ?? 'pppoe';

        // 1) RADIUS
        $this->radius->upsertUser($customer->username, $customer->password_plain, $group, $rate);

        // 2) MikroTik PPP secret
        if (!empty($customer->mikrotik_id)) {
            $this->mt->upsertPppSecret((int)$customer->mikrotik_id, $customer->username, $customer->password_plain, $svc, $profile);
        }
    }

    public function suspend(object $customer): void
    {
        $this->radius->suspend($customer->username);
        if (!empty($customer->mikrotik_id)) {
            $this->mt->removePppSecret((int)$customer->mikrotik_id, $customer->username);
        }
    }
}
