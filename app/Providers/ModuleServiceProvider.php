<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Auto-load all module service providers
        foreach (glob(base_path('modules/*/Providers/*.php')) as $file) {
            $moduleName = basename(dirname(dirname($file))); // Extract module name
            $providerClass = "Modules\\$moduleName\\Providers\\" . basename($file, '.php');

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
