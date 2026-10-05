<?php

namespace Modules\Dashboard\Providers;

use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/Dashboard/Routes/web/dashboard.php'));
        $this->loadViewsFrom(base_path('modules/Dashboard/Resources/views'), 'Dashboard');
    }

    public function register()
    {
        //
    }
}
