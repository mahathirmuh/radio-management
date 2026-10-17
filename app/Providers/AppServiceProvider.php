<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use App\Exceptions\Handler;
use App\Helpers\LogApps;
use App\Http\Middleware\CheckMenuPermission;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Request $request)
    {
        // Sanitize. The base pages print some values unescaped, so tags are stripped from their
        // input. Radio Management escapes on output everywhere ({{ }}, RadioUi.esc), so its input
        // is stored exactly as typed ("E & I", "a < b").
        if (! $request->is('radio-management', 'radio-management/*')) {
            $request->merge(LogApps::sanitizeInput($request->all()));
        }

        // An https address in APP_URL (the production server, behind its reverse proxy) is the address people open:
        // links are made with https whatever the request looked like when it reached the container. Otherwise a proxy
        // that is not named in TRUSTED_PROXIES leaves the pages linking their CSS/JS with http://, which the browser
        // blocks on an https page. (TRUSTED_PROXIES is still wanted: it gives the address of the visitor, which the
        // log of activity and the limit on tries of the login form go by.)
        if (str_starts_with(strtolower((string) config('app.url')), 'https://')) {
            URL::forceScheme('https');
        }

        $this->registerPermissionDirective();
    }

    /**
     * Blade directive mirroring the CheckMenuPermission middleware so the UI can
     * hide actions the current role cannot perform.
     *
     *   @permission('user-management.role-permission', 'Create')
     *       <x-global.btn-detail label="Add Role" .../>
     *   @endpermission
     *
     * Second arg is a permission name (Create/Read/Update/Delete); defaults to
     * Read. Mirrors the middleware's DEFAULT OPEN policy: routes/menus that are
     * not registered are treated as allowed.
     */
    private function registerPermissionDirective(): void
    {
        Blade::if('permission', function (string $menuKey, string $ability = 'Read') {
            $user = auth()->user();

            if (!$user) {
                return false;
            }

            // Super admin (role id 1) sees everything.
            if ($user->roles->contains('id', 1)) {
                return true;
            }

            $menu = CheckMenuPermission::menuForKey($menuKey);

            if (!$menu) {
                return true; // menu not registered -> default open
            }

            if (!$user->guardRole($menu->id)) {
                return false;
            }

            $permissionId = CheckMenuPermission::PERMISSION_IDS[$ability] ?? null;

            return $permissionId ? $user->hasMenuPermission($menu->id, $permissionId) : true;
        });
    }
}
