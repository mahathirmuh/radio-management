<?php

namespace Modules\Notifications\Providers;

use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/Notifications/Routes/web/notifications.php'));
        $this->loadViewsFrom(base_path('modules/Notifications/Resources/views'), 'Notifications');
    }
}
