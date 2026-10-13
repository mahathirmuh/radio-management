<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization\Company;
use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRegisteredController extends Controller
{

    protected $pageTitle;
    protected $menuItems;
    private $user;
    private $company;
    private $role;
    private $menuId;

    public function __construct()
    {
        $this->menuId = 98; //User Registered
        $this->pageTitle = "Users";
        $this->menuItems = [
            ['url' => '/', 'label' => 'Home', 'active' => false],
            ['url' => '/user-management', 'label' => 'User Management', 'active' => true],
            ['url' => '/user-registered', 'label' => 'User Registered', 'active' => true],
        ];

        $this->user = new User();
        $this->company = new Company();
        $this->role = new Role();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        if (!request()->user()->guardRole($this->menuId)) {
            abort(403);
        }

        // The form only offers what the caller may use: the same limits are enforced in store() / update().
        $caller = request()->user();
        $allowedCompanyIds = $this->allowedCompanyIds($caller);
        $assignableRoleIds = $this->assignableRoleIds($caller);

        $companies = $this->company::select('company_orangehr_code', 'name')
            ->when($allowedCompanyIds !== null, fn ($query) => $query->whereIn('id', $allowedCompanyIds))
            ->get();
        $roles = $this->role::query()
            ->when($assignableRoleIds !== null, fn ($query) => $query->whereIn('id', $assignableRoleIds))
            ->get();

        try {
            return view('UserManagement::user-registered.index', [
                'pageTitle' => $this->pageTitle,
                'menuItems' => $this->menuItems,
                'companies' => $companies,
                'positions' => User::POSITIONS,
                'signInReady' => User::signInReady(),
                'roles' => $roles,

            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function list(Request $request)
    {

        if (!request()->user()->guardRole($this->menuId)) {
            abort(403);
        }

        //Table Control
        $paginated = min(max((int) $request->get('paginated', 10), 1), 100);
        $search = $request->get('search', null);
        $signIn = User::signInReady();
        $sortable = array_merge(['id', 'name', 'email', 'active'], $signIn ? ['last_login_at'] : []);
        $orderBy = in_array($request->get('orderBy'), $sortable, true) ? $request->get('orderBy') : 'name';
        $orderDir = $request->get('orderDir') === 'desc' ? 'desc' : 'asc';


        //Data Control
        $companyIdList = $request->company != null
            ? $this->company::whereIn('company_orangehr_code', (array) $request->company)->pluck('id')
            : collect();

        // A role limited to some companies only ever sees those companies' users.
        $allowedCompanyIds = $this->allowedCompanyIds($request->user());

        $data = $this->user
            ->with('company', 'roles')

            ->when($allowedCompanyIds !== null, function ($query) use ($allowedCompanyIds) {
                return $query->whereIn('company_id', $allowedCompanyIds);
            })

            // Filter: by company
            ->when($request->company != null, function ($query) use ($companyIdList) {
                return $query->whereIn('company_id', $companyIdList);
            })

            // Filter: by status ('0' is inactive, see User::isActive())
            ->when(in_array($request->status, ['active', 'inactive'], true), function ($query) use ($request) {
                return $request->status === 'inactive'
                    ? $query->where('active', '0')
                    : $query->where(fn ($q) => $q->where('active', '!=', '0')->orWhereNull('active'));
            })

            // Filter: by position
            ->when($request->position != null, function ($query) use ($request) {
                return $query->whereIn('position', array_intersect((array) $request->position, User::POSITIONS));
            })

            //Filter: by role
            ->when($request->role != null, function ($query) use ($request) {
                return $query->whereHas('roles', function ($query) use ($request) {
                    if (! $this->isSuperAdmin($request->user())) {
                        $query->whereIn('role_id', $request->role)
                            ->whereNotIn('role_id', [1]);
                    } else {
                        $query->whereIn('role_id', $request->role);
                    }
                });
            })

            //Search
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })

            //Sorting (by id as well: rows that are equal keep one order from page to page)
            ->orderBy($orderBy, $orderDir)
            ->when($orderBy !== 'id', fn ($query) => $query->orderBy('id'))

            //Pagination
            ->paginate($paginated);

        $callerId = (int) $request->user()->id;
        $formatted = $data->getCollection()->map(function ($user) use ($callerId, $signIn) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company->name ?? '-',
                'position' => $user->position ?: '-',
                'role' => $user->roles->pluck('name')->implode(', ') ?? '-',
                'active' => $user->isActive(),
                // How the account last signed in ("Microsoft", "Password", ...); null while it never has.
                'sign_in' => $signIn ? $user->last_login_method : null,
                'last_sign_in' => $signIn ? $user->last_login_at?->format('Y-m-d H:i') : null,
                // The caller's own row: it is marked, and offers the edit only.
                'is_self' => (int) $user->id === $callerId,
            ];
        });

        return response()->json([
            'data' => $formatted,
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'path' => $request->url(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),

            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $this->user->with('company', 'roles')->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if ($denied = $this->denyUnlessManageable($request, $user)) {
            return $denied;
        }
        $assignable = $this->assignableRoleIds($request->user());

        return response()->json([
            'message' => 'User data retrieved successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_orangehr_code' => $user->company?->company_orangehr_code,
                'company_name' => $user->company?->name,
                'position' => $user->position,
                'role_id' => $user->roles->pluck('id'),
                'role_name' => $user->roles->pluck('name')->implode(', '),
                // Roles of this user that the caller's form does not list; an update leaves them alone.
                'other_role_count' => $assignable === null
                    ? 0
                    : $user->roles->pluck('id')->map(fn ($roleId) => (int) $roleId)->diff($assignable)->count(),
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-\.,]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->whereNull('deleted_at')],
            // The form asks for the position, not for the company any more: the company is the caller's own (see newUserCompany()).
            // A request that still names one is held to the same rules as before.
            'company_id' => ['nullable', 'string', 'exists:companies,company_orangehr_code'],
            'position' => ['required', 'string', Rule::in(User::POSITIONS)],
            'role_id' => ['required', 'array', 'min:1'],
            // Roles are soft-deleted: a deleted one must not be handed out (the user would be left without a usable role).
            'role_id.*' => [Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        if (! $this->positionReady()) {
            return response()->json(['message' => 'Kolom Position belum ada di database. Jalankan "php artisan migrate" dulu.'], 503);
        }

        // Deleting a user is a soft delete and the row keeps its unique e-mail:
        // adding the same person again brings that row back instead of inserting.
        $existing = $this->user->withTrashed()->where('email', $request->email)->first();

        $companySelected = filled($request->company_id)
            ? $this->company->where('company_orangehr_code', $request->company_id)->first()
            // An account that is brought back keeps the company it had; a new one gets the caller's.
            : $this->company->find($existing?->company_id ?? $this->newUserCompany($request->user()));
        if (!$companySelected) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => 'Company was not found on system, please contact administrator'
            ], 422);
        }

        if ($denied = $this->denyCompanyOutOfScope($request, $companySelected->id)
            ?? ($existing ? $this->denyUnlessManageable($request, $existing) : null)
            ?? $this->denySuperAdminGrant($request)
            ?? $this->denyRolesOutOfScope($request)) {
            return $denied;
        }

        try {
            DB::transaction(function () use ($request, $companySelected, $existing) {
                $attributes = [
                    'name' => $request->name,
                    'company_id' => $companySelected->id,
                    'position' => $request->position,
                    // Accounts sign in through SSO; a random hash keeps the e-mail + password form unusable for them.
                    'password' => bcrypt(Str::random(40)),
                    'remember_token' => null,
                    // NOT NULL columns without a default.
                    'verify_key' => '',
                    'mark_user' => '',
                    'active' => '1',
                ];

                if ($user = $existing) {
                    $user->restore();
                    $user->update($attributes);
                } else {
                    $user = $this->user->create($attributes + ['email' => $request->email]);
                }

                $user->roles()->sync($request->role_id);
            });

            return response()->json(["message" => "User Created Successfully"], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => 'The user could not be saved. Details are in the application log.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $assignable = $this->assignableRoleIds($request->user());

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-\.,]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            // Not asked for by the form any more: without it the company of the user stays what it is.
            'company_id' => ['nullable', 'string', 'exists:companies,company_orangehr_code'],
            'position' => ['required', 'string', Rule::in(User::POSITIONS)],
            // A caller with a limited set of roles may leave every box unticked: the roles of the
            // user that their form does not list stay. The resulting list is checked below.
            'role_id' => $assignable === null ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'role_id.*' => [Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        if (! $this->positionReady()) {
            return response()->json(['message' => 'Kolom Position belum ada di database. Jalankan "php artisan migrate" dulu.'], 503);
        }

        // Cari user berdasarkan ID
        $user = $this->user->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User Not Found!',
                'errors' => 'The user you are trying to update does not exist.'
            ], 404);
        }

        if ($denied = $this->denyUnlessManageable($request, $user)
            ?? $this->denySuperAdminGrant($request, $user)
            ?? $this->denyRolesOutOfScope($request, $user)) {
            return $denied;
        }

        // The company that was named, else the one the user has.
        $companySelected = filled($request->company_id)
            ? $this->company->where('company_orangehr_code', $request->company_id)->first()
            : $this->company->find($user->company_id);
        if (!$companySelected) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => 'Company was not found in the system, please contact administrator'
            ], 404);
        }
        if ($denied = $this->denyCompanyOutOfScope($request, $companySelected->id)) {
            return $denied;
        }

        // Roles of the user that a caller with a limited set of roles cannot manage (their form
        // does not list them): they stay as they are.
        $unmanaged = $assignable === null
            ? collect()
            : $user->roles->pluck('id')->map(fn ($roleId) => (int) $roleId)->diff($assignable);

        // Sign-in goes by e-mail, so whoever changes the address of an account can take it over,
        // and with it the roles the caller is not allowed to hand out.
        if ($unmanaged->isNotEmpty() && strcasecmp((string) $request->email, (string) $user->email) !== 0) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => ['You cannot change the e-mail of a user who holds roles you cannot manage.'],
            ], 403);
        }

        // The roles the user ends up with.
        $roleIds = collect((array) $request->role_id)->map(fn ($roleId) => (int) $roleId)->merge($unmanaged)->unique()->values();
        if ($roleIds->isEmpty()) {
            return response()->json(['message' => 'Invalid Input!', 'errors' => ['The role id field is required.']], 422);
        }
        if ($denied = $this->denyLiftingLimit($request, $user, $roleIds)) {
            return $denied;
        }

        try {
            DB::transaction(function () use ($request, $user, $companySelected, $roleIds) {
                // Update user data
                $user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'company_id' => $companySelected->id,
                    'position' => $request->position,
                ]);

                $user->roles()->sync($roleIds->all());
            });

            return response()->json(["message" => "User Updated Successfully"], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => 'The user could not be saved. Details are in the application log.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            // Deleting yourself would end your own access, and may leave the application without an admin.
            if ((int) $user->id === (int) $request->user()->id) {
                return response()->json(['message' => 'You cannot delete your own account.'], 422);
            }
            if ($denied = $this->denyUnlessManageable($request, $user)) {
                return $denied;
            }

            DB::transaction(function () use ($user) {
                // Hapus relasi role_user jika ada
                $user->roles()->detach();

                // Hapus user
                $user->delete();
            });

            return response()->json(['message' => 'User deleted successfully!'], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => 'The user could not be deleted. Details are in the application log.'], 500);
        }
    }

    /** Sets an account to active or inactive. An inactive account cannot sign in, and a session it has open ends. */
    public function status(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['active' => ['required', 'boolean']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid Input!', 'errors' => $validator->errors()->all()], 422);
        }

        $user = $this->user->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        // As with deleting: it would end your own access, and may leave the application without an admin.
        if ((int) $user->id === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }
        if ($denied = $this->denyUnlessManageable($request, $user)) {
            return $denied;
        }

        $active = $request->boolean('active');
        // A "remember me" cookie of the account must not bring it back in.
        $user->update(['active' => $active ? '1' : '0'] + ($active ? [] : ['remember_token' => null]));

        return response()->json(['message' => $active ? 'User activated.' : 'User deactivated.'], 200);
    }

    /**
     * Gives an account a password for the e-mail + password form of the login page (an account is made with a random
     * one, for Microsoft sign-in only). The password is the caller's to pass on; it is not shown or sent anywhere.
     */
    public function password(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid Input!', 'errors' => $validator->errors()->all()], 422);
        }

        $user = $this->user->with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($denied = $this->denyUnlessManageable($request, $user)) {
            return $denied;
        }

        // Whoever sets the password of an account can sign in as it, and so use the roles it holds:
        // the same rule as for changing its e-mail (see update()).
        $assignable = $this->assignableRoleIds($request->user());
        if ($assignable !== null && $user->roles->pluck('id')->map(fn ($roleId) => (int) $roleId)->diff($assignable)->isNotEmpty()) {
            return response()->json(['message' => 'You cannot set the password of a user who holds roles you cannot manage.'], 403);
        }

        $user->update(['password' => bcrypt($request->password), 'remember_token' => null]);

        return response()->json(['message' => 'Password saved.'], 200);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->roles->contains('id', 1);
    }

    /** The caller's roles that are limited to some companies (func_role HO / SITE / COMPANY). */
    private function scopedRoles(User $caller)
    {
        return $this->isSuperAdmin($caller)
            ? collect()
            : $caller->roles->filter(fn ($role) => $role->func_role !== 'ALL')->values();
    }

    /**
     * Company ids the caller may manage, or null when the caller is not limited (Super Admin, or
     * only roles of type ALL). A caller holding a limited role stays limited whatever other roles
     * they have: the result must not depend on which role the database happens to return first.
     */
    private function allowedCompanyIds(User $caller)
    {
        $scoped = $this->scopedRoles($caller);
        if ($scoped->isEmpty()) {
            return null;
        }
        $codes = $scoped->flatMap(fn ($role) => (array) ($caller->haveCompanyOrangeHr($role) ?? []))->unique()->values();

        return $this->company::whereIn('company_orangehr_code', $codes)->pluck('id');
    }

    /**
     * What a limited role reaches, as strings: the site owner of an HO role, the site ids of a
     * SITE role, the company ids of a COMPANY role.
     *
     * @return string[]
     */
    private function roleReach($role): array
    {
        if ($role->func_role === 'HO') {
            return [(string) $role->setting_role];
        }

        return array_values(array_map('strval', array_filter((array) json_decode((string) $role->setting_role, true), 'is_scalar')));
    }

    /**
     * Role ids the caller may hand out, or null when not limited. A caller limited to some
     * companies only hands out limited roles of the same kind that stay inside their own reach.
     * A role that is not limited, or that also covers a company outside it, would give someone
     * (or the caller) more than the caller has.
     */
    private function assignableRoleIds(User $caller)
    {
        $scoped = $this->scopedRoles($caller);
        if ($scoped->isEmpty()) {
            return null;
        }

        // The caller's reach per kind of role, over all their limited roles.
        $reach = [];
        foreach ($scoped as $own) {
            $reach[$own->func_role] = array_merge($reach[$own->func_role] ?? [], $this->roleReach($own));
        }

        return $this->role::query()
            ->whereIn('func_role', array_keys($reach))
            ->get(['id', 'func_role', 'setting_role'])
            ->filter(function ($role) use ($reach) {
                $covers = $this->roleReach($role);

                return $covers !== [] && array_diff($covers, $reach[$role->func_role]) === [];
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /** Super Admin accounts are managed by Super Admins only; scoped roles stay inside their companies. */
    private function denyUnlessManageable(Request $request, User $target)
    {
        $caller = $request->user();
        if ($this->isSuperAdmin($caller)) {
            return null;
        }

        $allowed = $this->allowedCompanyIds($caller);
        if ($this->isSuperAdmin($target) || ($allowed !== null && !$allowed->contains($target->company_id))) {
            return response()->json(['message' => 'You are not allowed to manage this user.'], 403);
        }

        return null;
    }

    /**
     * Whether the column of the position is there. The code of this folder is live as soon as it is saved, the migration
     * is run by hand: until then nothing is saved (and the reason is said), instead of failing. (Asked once a minute.)
     */
    private function positionReady(): bool
    {
        return Cache::remember('users-position-column', 60, fn () => Schema::hasColumn('users', 'position'));
    }

    /**
     * The company of a user that is added without one being named (the form does not ask for it): the caller's own,
     * or, for a caller limited to companies that do not include their own, the first of those.
     */
    private function newUserCompany(User $caller): ?int
    {
        $allowed = $this->allowedCompanyIds($caller);
        if ($allowed === null || $allowed->contains($caller->company_id)) {
            return (int) $caller->company_id;
        }

        return $allowed->isEmpty() ? null : (int) $allowed->first();
    }

    /** A role limited to some companies cannot create a user in, or move a user to, another company. */
    private function denyCompanyOutOfScope(Request $request, int $companyId)
    {
        $allowed = $this->allowedCompanyIds($request->user());
        if ($allowed !== null && !$allowed->contains($companyId)) {
            return response()->json(['message' => 'You are not allowed to manage users of this company.'], 403);
        }

        return null;
    }

    /** A caller with a limited set of roles cannot add a role outside it (roles the user already has may stay). */
    private function denyRolesOutOfScope(Request $request, ?User $target = null)
    {
        $assignable = $this->assignableRoleIds($request->user());
        if ($assignable === null) {
            return null;
        }
        $held = $target ? $target->roles->pluck('id')->map(fn ($id) => (int) $id) : collect();
        $added = collect((array) $request->role_id)->map(fn ($id) => (int) $id)->diff($assignable)->diff($held);

        if ($added->isNotEmpty()) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => ['You are not allowed to assign this role.'],
            ], 403);
        }

        return null;
    }

    /**
     * A caller who is limited to some companies must not leave an account less limited than it
     * was: not their own (their limited roles stay), and not that of a user who would keep
     * access to User Management through a role that is not limited.
     *
     * @param \Illuminate\Support\Collection<int, int> $roleIds the roles the target would end up with
     */
    private function denyLiftingLimit(Request $request, User $target, $roleIds)
    {
        $caller = $request->user();
        $own = $this->scopedRoles($caller);
        if ($own->isEmpty()) {
            return null; // the caller is not limited
        }

        if ((int) $target->id === (int) $caller->id) {
            $dropped = $own->pluck('id')->map(fn ($id) => (int) $id)->diff($roleIds);

            return $dropped->isEmpty() ? null : response()->json([
                'message' => 'Invalid Input!',
                'errors' => ['You cannot remove your own role.'],
            ], 422);
        }

        $wasLimited = $target->roles->contains(fn ($role) => $role->func_role !== 'ALL');
        if (! $wasLimited) {
            return null;
        }
        $staysLimited = $this->role::query()->whereIn('id', $roleIds)->where('func_role', '!=', 'ALL')->exists();
        $keepsUserManagement = DB::table('menu_role')->where('menu_id', $this->menuId)->whereIn('role_id', $roleIds)->exists();

        return $staysLimited || ! $keepsUserManagement ? null : response()->json([
            'message' => 'Invalid Input!',
            'errors' => ['Removing this role would lift the company limit of this user. Ask an administrator who is not limited to a company.'],
        ], 422);
    }

    /**
     * Only a Super Admin may hand out the Super Admin role, and nobody may take it
     * away from their own account (that could leave the application without an admin).
     */
    private function denySuperAdminGrant(Request $request, ?User $target = null)
    {
        $caller = $request->user();
        $grantsSuperAdmin = in_array(1, array_map('intval', (array) $request->role_id), true);

        if ($grantsSuperAdmin && !$this->isSuperAdmin($caller)) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => ['Only a Super Admin can assign the Super Admin role.'],
            ], 403);
        }

        if ($target && (int) $target->id === (int) $caller->id && $this->isSuperAdmin($caller) && !$grantsSuperAdmin) {
            return response()->json([
                'message' => 'Invalid Input!',
                'errors' => ['You cannot remove the Super Admin role from your own account.'],
            ], 422);
        }

        return null;
    }
}
