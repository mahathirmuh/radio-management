<?php

namespace Modules\RadioManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\RadioManagement\Console\ImportRadioRegister;
use Modules\RadioManagement\Console\UnlockRadioRegister;

class RadioManagementServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/RadioManagement/Routes/web/radio-management.php'));
        $this->loadViewsFrom(base_path('modules/RadioManagement/Resources/views'), 'RadioManagement');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportRadioRegister::class, UnlockRadioRegister::class]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(base_path('modules/RadioManagement/Config/radio.php'), 'radio');
    }
}
