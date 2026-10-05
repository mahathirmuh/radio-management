<?php

namespace Modules\UserManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\UserManagement\Repositories\UserManagementRepository;
use Modules\UserManagement\Repositories\UserManagementInterface;

class UserManagementServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/UserManagement/Routes/web/user-management.php'));
        $this->loadViewsFrom(base_path('modules/UserManagement/Resources/views'), 'UserManagement');
    }

    public function register()
    {
        // ✅ Automatically bind all repositories
        $repositoryPath = base_path("modules/UserManagement/Repositories");

        if (is_dir($repositoryPath)) {
            foreach (scandir($repositoryPath) as $file) {
                if (preg_match('/^(.+)Interface\.php$/', $file, $matches)) {
                    $interface = "Modules\\UserManagement\\Repositories\\{$matches[1]}Interface";
                    $repository = "Modules\\UserManagement\\Repositories\\{$matches[1]}Repository";

                    if (interface_exists($interface) && class_exists($repository)) {
                        $this->app->bind($interface, $repository);
                    }
                }
            }
        }
    }
}
