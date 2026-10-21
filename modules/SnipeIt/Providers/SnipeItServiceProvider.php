<?php

namespace Modules\SnipeIt\Providers;

use Illuminate\Support\ServiceProvider;

class SnipeItServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/SnipeIt/Routes/web/snipe-it.php'));
        $this->loadViewsFrom(base_path('modules/SnipeIt/Resources/views'), 'SnipeIt');
        $this->loadTranslationsFrom(base_path('modules/SnipeIt/Resources/lang'), 'SnipeIt');
    }
}
