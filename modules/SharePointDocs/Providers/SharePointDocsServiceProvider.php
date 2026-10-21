<?php

namespace Modules\SharePointDocs\Providers;

use Illuminate\Support\ServiceProvider;

class SharePointDocsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(base_path('modules/SharePointDocs/Routes/web/sharepoint-docs.php'));
        $this->loadViewsFrom(base_path('modules/SharePointDocs/Resources/views'), 'SharePointDocs');
        $this->loadTranslationsFrom(base_path('modules/SharePointDocs/Resources/lang'), 'SharePointDocs');
    }

    public function register()
    {
        $this->mergeConfigFrom(base_path('modules/SharePointDocs/Config/sharepoint-docs.php'), 'sharepoint-docs');
    }
}
