<?php

namespace Modules\AuditLog\Providers;

use Illuminate\Support\ServiceProvider;

class AuditLogServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/AuditLog/Routes/web/audit-log.php'));
        $this->loadViewsFrom(base_path('modules/AuditLog/Resources/views'), 'AuditLog');
        $this->loadTranslationsFrom(base_path('modules/AuditLog/Resources/lang'), 'AuditLog');
    }
}
