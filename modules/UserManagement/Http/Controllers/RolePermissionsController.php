<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization\Company;
use App\Models\UserManagement\Menu;
use App\Models\UserManagement\MenuRolePermission;
use App\Models\UserManagement\Permission;
use App\Models\UserManagement\Role;
use App\Models\Organization\Site;
use App\Models\UserManagement\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class RolePermissionsController extends Controller
{
    private $pageTitle;
    private $menuId;
    private $menuItems;
    private $user;
    private $company;
    private $site;
    private $role;
    private $permission;
    private $menu;

    public function __construct()
    {
        $this->middleware('auth');
        $this->pageTitle = "Role & Permission";
        $this->menuId = 99;
        $this->menuItems = [
            ['url' => '/', 'label' => 'Home', 'active' => false],
            ['url' => '/employee', 'label' => 'User Management - Role & Permission', 'active' => true],
        ];

        $this->user = new User();
        $this->company = new Company();
        $this->site = new Site();
        $this->role = new Role();
        $this->permission = new Permission();
        $this->menu = new Menu();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!request()->user()->guardRole($this->menuId)) {
            abort(403);
        }

        $companies = $this->company::with('users')->get();
        $menus = $this->menu::with('roles')->get();
        // One site per owner. "SELECT * ... GROUP BY site_owner" only works on MySQL;
        // SQL Server rejects it, so the grouping is done on the collection instead.
        $hos = $this->site->orderBy('id')->get()->unique('site_owner')->values();

        $sites = $this->site->get();
        $permission = $this->permission::get();

        return view('UserManagement::role-permission.index', [
            'pageTitle' => $this->pageTitle,
            'menuItems' => $this->menuItems,
            'companies' => $companies,
            'sites' => $sites,
            'hos' => $hos,
            // 'role' => $role,
            'menus' => $menus,
            'permission' => $permission,

        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function list(Request $request)
    {
        // Authorization
        if (!$request->user()->guardRole($this->menuId)) {
            abort(403);
        }

        // Table control
        $paginated = min(max((int) $request->get('paginated', 10), 1), 100);
        $search = $request->get('search');
        $orderBy = in_array($request->get('orderBy'), ['id', 'name', 'func_role'], true) ? $request->get('orderBy') : 'id';
        $orderDir = $request->get('orderDir') === 'desc' ? 'desc' : 'asc';

        // User role and company scope
        $user = $request->user();
        $getRole = $user->getRole();
        $allowedCompanyCodes = $user->haveCompanyOrangeHr($getRole) ?? [];

        // Determine filtered companies
        $companyIdList = $request->filled('company')
            ? $this->company::whereIn('company_orangehr_code', $request->company)->pluck('id')
            : $this->company::whereIn('company_orangehr_code', $allowedCompanyCodes)->pluck('id');

        // Base query
        $query = $this->role::query();
        if ($getRole->id !== 1) {
            $query->where('id', '!=', 1);
        }

        // Optional search
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Sorting
        $query->orderBy($orderBy, $orderDir);

        // Paginate. With a company filter the rows are filtered after formatting (below),
        // so every role is needed, not just the first page.
        $filtered = $request->filled('company');
        $roles = $filtered ? $query->get() : $query->paginate($paginated);

        // Format result
        $formatted = ($filtered ? $roles : $roles->getCollection())->map(function ($role, $index) use ($getRole) {
            $companyObject = [];

            switch ($role->func_role) {
                case 'ALL':
                    $companyObject = $this->company->get(['id', 'name', 'company_orangehr_code'])->toArray();
                    break;
                case 'HO':
                    $sites = $this->site->where('site_owner', $role->setting_role)->pluck('id');
                    $companyObject = $this->company->whereIn('site_id', $sites)->get(['id', 'name', 'company_orangehr_code'])->toArray();
                    break;
                case 'SITE':
                    $setting = json_decode($role->setting_role, true);
                    if (is_array($setting)) {
                        $companyObject = $this->company->whereIn('site_id', $setting)->get(['id', 'name', 'company_orangehr_code'])->toArray();
                    }
                    break;
                case 'COMPANY':
                    $setting = json_decode($role->setting_role, true);
                    if (is_array($setting)) {
                        $companyObject = $this->company->whereIn('id', $setting)->get(['id', 'name', 'company_orangehr_code'])->toArray();
                    }
                    break;
            }

            return [
                'id' => $role->id,
                'name' => $role->name,
                'company_id' => $role->company_id,
                'home_page' => $role->home_page,
                'func_role' => $role->func_role,
                'setting_role' => $role->setting_role,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
                'deleted_at' => $role->deleted_at,
                'company' => $companyObject,
                'user_count' => $role->users->count(),
                'action' => [
                    'id' => $role->id,
                    'is_active' => ($getRole->id == 1 && $role->id == 1) ? 1 : ($role->id == 1 ? 0 : 1),
                ],
                'DT_RowIndex' => $index + 1,
            ];
        });

        if ($request['company'] != null) {
            $requestedCompanies = $request['company'];
            $formatted = $formatted->filter(function ($role) use ($requestedCompanies) {
                // $role['company'] is an array of companies
                foreach ($role['company'] as $company) {
                    if (in_array($company['company_orangehr_code'], $requestedCompanies)) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        if ($request->filled('company')) {
            $page = max((int) $request->get('page', 1), 1);
            $perPage = $paginated;
            $total = $formatted->count();
            $lastPage = (int) ceil($total / $perPage);

            $results = $formatted->slice(($page - 1) * $perPage, $perPage)->values();

            return response()->json([
                'data' => $results,
                'links' => [
                    'first' => 1,
                    'last' => $lastPage,
                    'prev' => $page > 1 ? $page - 1 : null,
                    'next' => $page < $lastPage ? $page + 1 : null,
                ],
                'meta' => [
                    'current_page' => $page,
                    'from' => $total === 0 ? null : (($page - 1) * $perPage + 1),
                    'last_page' => $lastPage,
                    'path' => $request->url(),
                    'per_page' => $perPage,
                    'to' => $total === 0 ? null : min($page * $perPage, $total),
                    'total' => $total,
                ],
            ]);
        } else {
            return response()->json([
                'data' => $formatted,
                'links' => [
                    'first' => $roles->url(1),
                    'last' => $roles->url($roles->lastPage()),
                    'prev' => $roles->previousPageUrl(),
                    'next' => $roles->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'from' => $roles->firstItem(),
                    'last_page' => $roles->lastPage(),
                    'path' => $request->url(),
                    'per_page' => $roles->perPage(),
                    'to' => $roles->lastItem(),
                    'total' => $roles->total(),
                ],
            ]);
        }
    }

    public function create()
    {
        //
    }

    /**
     * Sync menu_role_permission rows for a role, restricted to menus the role actually has access to.
     */
    private function syncMenuPermissions(Role $role, array $menuList, array $menuPermissionInput)
    {
        MenuRolePermission::where('role_id', $role->id)->delete();

        foreach ($menuPermissionInput as $menuId => $permissionIds) {
            if (!in_array($menuId, $menuList)) {
                continue;
            }

            foreach ($permissionIds as $permissionId) {
                MenuRolePermission::create([
                    'role_id' => $role->id,
                    'menu_id' => $menuId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:255', // Example rules for role_name (required, string, maximum length: 255)
            // The form does not ask for the type any more: a role that is made without one is for every company (ALL).
            // A request that still names a type is held to it, as before.
            'role_type' => 'nullable|string|in:COMPANY,HO,SITE,ALL',
            'home_page' => 'required',
        ]);
        if ($validator->fails()) {
            //Validator
            $errors = $validator->errors()->all();

            return response()->json(['message' => 'Invalid Input!', 'errors' => $errors], 422);
        } else {
            try {

                $role_type = $request->role_type ?: 'ALL';
                $setting_role = null;
                switch ($role_type) {
                    case 'ALL':
                        $setting_role = null;

                        break;
                    case 'HO':
                        $setting_role = $request->role_ho;

                        break;
                    case 'SITE':
                        $setting_role = json_encode($request->role_site);
                        break;
                    case 'COMPANY':
                        $setting_role = json_encode($request->role_company);
                        break;
                }

                DB::transaction(function () use ($request, $setting_role, $role_type) {

                    $data = $this->role->create([
                        'name' => $request->role_name,
                        'func_role' => $role_type,
                        'home_page' => $request->home_page,
                        'setting_role' => $setting_role
                    ]);

                    $data->menus()->attach($request->menu_list);

                    $this->syncMenuPermissions($data, $request->menu_list ?? [], $request->input('menu_permission', []));
                });

                return response()->json(["message" => "Data Updated Succesfully"], 200);
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // The Super Admin role itself is only visible to, and changeable by, Super Admins.
        abort_if((int) $id === 1 && ! request()->user()->roles->contains('id', 1), 403);

        $data = $this->role->with('menus')->where('id', $id)->first();

        $role_ho = null;
        $role_site = null;
        $role_company = null;;

        switch ($data->func_role) {
            case 'ALL':
                $role_ho = null;
                $role_site = null;
                $role_company = null;
                break;
            case 'HO':
                $role_ho = $data->setting_role;
                $role_site = null;
                $role_company = null;

                break;
            case 'SITE':
                $role_ho = null;
                $role_site = json_decode($data->setting_role);
                $role_company = null;

                break;
            case 'COMPANY':
                $role_ho = null;
                $role_site = null;
                $role_company = json_decode($data->setting_role);
                break;
        }


        $menuPermission = MenuRolePermission::where('role_id', $id)
            ->get()
            ->groupBy('menu_id')
            ->map(fn ($items) => $items->pluck('permission_id'));

        $collection = [
            'role_name' => $data->name,
            'role_type' => $data->func_role,
            'home_page' => $data->home_page,
            'role_ho' => $role_ho,
            'role_site' => $role_site,
            'role_company' => $role_company,
            'menu_list' => $data->menus,
            'menu_permission' => $menuPermission,
        ];




        return response()->json([
            'data' => $collection,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // The Super Admin role itself is only visible to, and changeable by, Super Admins.
        abort_if((int) $id === 1 && ! request()->user()->roles->contains('id', 1), 403);

        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|max:255', // Example rules for role_name (required, string, maximum length: 255)
            // Not asked for by the form any more: without it the type of the role, and the companies it is limited to, stay what they are.
            'role_type' => 'nullable|string|in:COMPANY,HO,SITE,ALL',
            'home_page' => 'required',
        ]);
        if ($validator->fails()) {
            //Validator
            $errors = $validator->errors()->all();
            return response()->json(['message' => 'Invalid Input!', 'errors' => $errors], 422);
        } else {
            try {

                $setting_role = null;
                switch ($request->role_type) {
                    case 'ALL':
                        $setting_role = null;

                        break;
                    case 'HO':
                        $setting_role = $request->role_ho;
                        break;
                    case 'SITE':
                        $setting_role = json_encode($request->role_site);
                        break;
                    case 'COMPANY':
                        $setting_role = json_encode($request->role_company);
                        break;
                }


                DB::transaction(function () use ($request, $id, $setting_role) {


                    $data = $this->role->with('menus')->where('id', $id)->first();
                    $data->update([
                        'name' => $request->role_name,
                        'home_page' => $request->home_page,
                    ] + ($request->filled('role_type') ? [
                        'func_role' => $request->role_type,
                        'setting_role' => $setting_role,
                    ] : []));

                    // Sync the menu IDs for the role
                    $data->menus()->sync($request->menu_list);

                    $this->syncMenuPermissions($data, $request->menu_list ?? [], $request->input('menu_permission', []));
                });

                return response()->json(["message" => "Data Updated Succesfully"], 200);
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // The Super Admin role itself is only visible to, and changeable by, Super Admins.
        abort_if((int) $id === 1 && ! request()->user()->roles->contains('id', 1), 403);

        try {
            $data = $this->role->with('users')->findOrFail($id);

            if ($data->users->isNotEmpty()) {
                return response()->json(["message" => "Role still assigned to user"], 500);
            } else {

                DB::transaction(function () use ($data) {
                    $data->delete();
                });
                return response()->json(["message" => "Data Deleted Successfully"], 200);
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
