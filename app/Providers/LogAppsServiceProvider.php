<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LogAppsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
    require_once app_path() . '/Helpers/LogApps.php';
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
