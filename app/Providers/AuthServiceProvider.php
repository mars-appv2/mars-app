<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

use App\Models\Mikrotik;
use App\Policies\MikrotikPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Mikrotik::class => MikrotikPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Admin auto-allow semua ability
        Gate::before(function ($user, $ability) {
            return (method_exists($user,'hasRole') && $user->hasRole('admin')) ? true : null;
        });
    }
}
