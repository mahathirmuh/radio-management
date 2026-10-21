<?php

namespace Modules\SnipeIt\Providers;

use Illuminate\Support\ServiceProvider;

class SnipeItServiceProvider extends ServiceProvider
{
    public function register()
    {
        // What the fields and ids of Snipe-IT's history stand for: the lists it looks them up in are asked once per request.
        $this->app->scoped(\Modules\SnipeIt\Services\Names::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/SnipeIt/Routes/web/snipe-it.php'));
        $this->loadViewsFrom(base_path('modules/SnipeIt/Resources/views'), 'SnipeIt');
        $this->loadTranslationsFrom(base_path('modules/SnipeIt/Resources/lang'), 'SnipeIt');
    }
}
