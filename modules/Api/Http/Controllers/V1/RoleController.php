<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\Menu;
use App\Models\UserManagement\MenuRolePermission;
use App\Models\UserManagement\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\RadioManagement\Services\Like;

/**
 * The roles of Role & Permission, read only: roles are made and changed on the page. The role Super Admin is shown
 * to a Super Admin only, as on the page.
 *
 * @tags Roles
 */
class RoleController extends ApiController
{
    /**
     * List roles
     *
     * With the number of people holding each (API clients not counted).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['id', 'name'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) $request->input('search'));

        /** @var LengthAwarePaginator $roles */
        $roles = Role::query()
            ->withCount(['users as people_count' => fn ($users) => $users->people()])
            ->when(! $this->superAdmin($request), fn ($query) => $query->where('id', '!=', 1))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => Like::any($q, ['name'], $search)))
            ->orderBy($request->input('sort', 'name'), $request->input('direction') === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($this->perPage($request));
        $homes = Menu::query()->whereIn('id', $roles->getCollection()->pluck('home_page')->filter(fn ($id) => is_numeric($id)))->get(['id', 'title', 'route'])->keyBy('id');

        return response()->json([
            'data' => $roles->getCollection()->map(fn (Role $role) => $this->summary($role, $homes))->values(),
            'links' => ['first' => $roles->url(1), 'last' => $roles->url($roles->lastPage()), 'prev' => $roles->previousPageUrl(), 'next' => $roles->nextPageUrl()],
            'meta' => [
                'current_page' => $roles->currentPage(), 'from' => $roles->firstItem(), 'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(), 'to' => $roles->lastItem(), 'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Show a role
     *
     * What it may do: each menu it opens, with its permissions there (Read, Create, Update, Delete). A menu with none
     * of them is opened only.
     */
    public function show(Request $request, int $role): JsonResponse
    {
        $found = Role::query()->withCount(['users as people_count' => fn ($users) => $users->people()])->find($role);
        abort_if($found === null || ((int) $found->id === 1 && ! $this->superAdmin($request)), 404);

        $granted = MenuRolePermission::where('role_id', $found->id)->get()->groupBy('menu_id')
            ->map(fn ($rows) => $rows->pluck('permission_id')->map(fn ($id) => (int) $id)->all());
        $menus = Menu::query()->whereIn('id', DB::table('menu_role')->where('role_id', $found->id)->select('menu_id'))
            ->orderByRaw('COALESCE(parent_id, id)')->orderBy('order')->get(['id', 'title', 'route', 'parent_id']);
        $homes = Menu::query()->whereKey(is_numeric($found->home_page) ? (int) $found->home_page : 0)->get(['id', 'title', 'route'])->keyBy('id');

        return response()->json(['data' => $this->summary($found, $homes) + [
            'menus' => $menus->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'title' => $menu->title,
                /** The page of the menu (null for a group of pages). */
                'route' => $menu->route,
                'parent_id' => $menu->parent_id,
                'permissions' => array_keys(array_filter(CheckMenuPermission::PERMISSION_IDS, fn ($id) => in_array($id, $granted[$menu->id] ?? [], true))),
            ])->values(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function summary(Role $role, $homes): array
    {
        $home = is_numeric($role->home_page) ? ($homes[(int) $role->home_page] ?? null) : null;

        return [
            'id' => $role->id,
            'name' => $role->name,
            /** The page its holders land on after signing in. */
            'home_page' => $home === null ? null : ['id' => $home->id, 'title' => $home->title, 'route' => $home->route],
            /** "ALL", or limited to some companies: "HO", "SITE", "COMPANY". */
            'scope' => $role->func_role ?: 'ALL',
            'people_count' => (int) $role->people_count,
        ];
    }

    private function superAdmin(Request $request): bool
    {
        return $request->user()->roles->contains('id', 1);
    }
}
