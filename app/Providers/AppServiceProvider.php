<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Hindari error index terlalu panjang pada MySQL/MariaDB lama
        Schema::defaultStringLength(191);
    }
}
