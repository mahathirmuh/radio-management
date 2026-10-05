<?php

namespace Modules\ApprovalManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ApprovalManagement\Repositories\ApprovalManagementRepository;
use Modules\ApprovalManagement\Repositories\ApprovalManagementInterface;

class ApprovalManagementServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/ApprovalManagement/Routes/web/approval-management.php'));
        $this->loadViewsFrom(base_path('modules/ApprovalManagement/Resources/views'), 'ApprovalManagement');
    }

        public function register()
    {
        // ✅ Automatically bind all repositories
        $repositoryPath = base_path("modules/ApprovalManagement/Repositories");

        if (is_dir($repositoryPath)) {
            foreach (scandir($repositoryPath) as $file) {
                if (preg_match('/^(.+)Interface\.php$/', $file, $matches)) {
                    $interface = "Modules\\ApprovalManagement\\Repositories\\{$matches[1]}Interface";
                    $repository = "Modules\\ApprovalManagement\\Repositories\\{$matches[1]}Repository";

                    if (interface_exists($interface) && class_exists($repository)) {
                        $this->app->bind($interface, $repository);
                    }
                }
            }
        }
    }
}