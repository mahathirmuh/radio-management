<?php

namespace App\Services;

use App\Models\UserManagement\Menu;
use App\Models\UserManagement\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * The path at the top of a page (main.navbar.navbar): "Dashboard › Master Data Radio › Tipe Radio". The dashboard comes
 * first for whoever may open it; then the group and the entry of the sidebar the page belongs to (any route of the
 * entry's menu key, as the sidebar marks it: the detail of a radio belongs to Register Radio); then the page itself when
 * it is deeper than its entry ("Radio 1053"). A page that belongs to no entry (Notifikasi) is the dashboard and its name.
 */
final class Breadcrumbs
{
    /**
     * @param  string  $page  what the page calls itself (@section('crumb'), else its title); '' for nothing
     * @return list<array{label: string, url: ?string}> the last one is the page that is open (url null)
     */
    public static function for(Request $request, ?User $user, string $page): array
    {
        $current = (string) $request->route()?->getName();
        $entry = self::entry($current);
        $crumbs = [];

        if ($current !== 'dashboard.index' && $user !== null && Route::has('dashboard.index') && MenuAccess::allows($user, 'dashboard.index')) {
            $crumbs[] = ['label' => Menu::routeLabel('dashboard.index', 'Dashboard'), 'url' => route('dashboard.index')];
        }

        if ($entry === null) {
            return $page === '' ? $crumbs : [...$crumbs, ['label' => $page, 'url' => null]];
        }

        if ($entry->parent !== null) {
            $crumbs[] = ['label' => $entry->parent->label(), 'url' => null];
        }
        // On a page below the entry, the entry leads back to its list, and the page names itself after it.
        $below = $current !== $entry->route && $page !== '' && $page !== $entry->label();
        $crumbs[] = ['label' => $entry->label(), 'url' => $below ? route($entry->route) : null];
        if ($below) {
            $crumbs[] = ['label' => $page, 'url' => null];
        }

        return $crumbs;
    }

    /** The entry of the sidebar that the route belongs to: the one with the longest menu key that covers it. */
    private static function entry(string $current): ?Menu
    {
        if ($current === '') {
            return null;
        }

        return Menu::query()->whereNotNull('route')->with('parent')->get()
            ->filter(fn (Menu $menu) => Route::has($menu->route)
                && ($current === $menu->route || Str::startsWith($current, Str::beforeLast($menu->route, '.') . '.')))
            ->sortByDesc(fn (Menu $menu) => strlen(Str::beforeLast($menu->route, '.')))
            ->first();
    }
}
