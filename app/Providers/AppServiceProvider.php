<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
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
