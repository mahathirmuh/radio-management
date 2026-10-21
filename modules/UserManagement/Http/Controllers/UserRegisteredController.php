<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckMenuPermission;
use App\Models\Organization\Company;
use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use App\Services\Directory\DirectoryUnavailable;
use App\Services\Directory\LdapAuthenticator;
use Modules\UserManagement\Services\EmployeeDirectory;
use Modules\UserManagement\Services\UserScope;

use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRegisteredController extends Controller
{
    /**
     * A name: letters (of any script: a name may come from the employee directory as it is written there), numbers,
     * spaces, . , - ' and brackets: most display names in Active Directory end in a company tag ("Budi Santoso [MTI]"),
     * which Find employee fills in as it is.
     */
    private const NAME_RULE = "regex:/^[\\pL\\pM0-9\\s\\-\\.,'\x{2019}()\\[\\]{}]+$/u";

    /** The accounts ticked in the list that one bulk action takes at most. */
    public const BULK_MAX = 100;

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

    /** What this page says, in the language of the request (UserManagement::users.messages). */
    private static function say(string $key, array $replace = []): string
    {
        return __('UserManagement::users.messages.' . $key, $replace);
    }

    /** What is said when a name, a directory username or the sign-in method that was sent is not one. */
    private static function messages(): array
    {
        return [
            'name.regex' => self::say('name_format'),
            'ldap_username.regex' => self::say('directory_format'),
            'sign_in_method.in' => self::say('method_unavailable'),
        ];
    }

    /** The names of the fields in the messages of the validation. */
    private static function attributes(): array
    {
        $names = __('UserManagement::users.attributes');

        return is_array($names) ? $names : [];
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
                'signInReady' => User::signInReady(),
                'roles' => $roles,
                // The role the form starts with: the one that only reads, where the caller may hand it out.
                'defaultRoleId' => $roles->firstWhere('name', 'Viewer Radio')?->id,
                // "Find employee": only where there is a directory to search, and for who may add or change a user. Where it
                // searches follows the sign-in method that is chosen (see lookupSources()).
                'directoryAvailable' => app(EmployeeDirectory::class)->isAvailable() && $this->mayChangeUsers($caller),
                'directoryMinimum' => app(EmployeeDirectory::class)->minimum(),
                'lookupSources' => $this->lookupSources($caller),
                // One method is no choice: with Microsoft alone the form does not ask.
                'signInMethods' => $this->offeredSignInMethods(),
                'signInMeanings' => __('UserManagement::users.sign_in_meanings'),
                // Whether Active Directory is set up here (LDAP_URL, LDAP_BIND_DN): the form says so when it is not.
                'ldapConfigured' => app(LdapAuthenticator::class)->isConfigured(),

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

        // People only: the accounts of applications (API clients) are on the page API Tokens.
        $data = $this->user->people()
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
        $scope = app(UserScope::class);
        $formatted = $data->getCollection()->map(function ($user) use ($callerId, $signIn, $scope, $request) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company->name ?? '-',
                'role' => $user->roles->pluck('name')->implode(', ') ?? '-',
                'active' => $user->isActive(),
                // How the account gets in: LDAP, Microsoft, Both or Pending (User::signInMethod()).
                'sign_in' => $user->signInMethod(),
                // It signs in with its directory password: there is none to set here.
                'directory' => $user->signsInWithDirectory(),
                'last_sign_in' => $signIn ? $user->last_login_at?->format('Y-m-d H:i') : null,
                // The caller's own row: it is marked, and offers the edit only.
                'is_self' => (int) $user->id === $callerId,
                // Whether it may be ticked for the bulk actions (deactivate, delete): not one's own, and one the caller manages.
                'manageable' => (int) $user->id !== $callerId && $scope->mayManage($request->user(), $user),
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
        // People only: API clients are managed on the page API Tokens.
        $user = $this->user->people()->with('company', 'roles')->find($id);

        if (!$user) {
            return response()->json(['message' => self::say('not_found')], 404);
        }
        if ($denied = $this->denyUnlessManageable($request, $user)) {
            return $denied;
        }
        $assignable = $this->assignableRoleIds($request->user());

        return response()->json([
            'message' => self::say('retrieved'),
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_orangehr_code' => $user->company?->company_orangehr_code,
                'company_name' => $user->company?->name,
                'active' => $user->isActive(),
                'is_self' => (int) $user->id === (int) $request->user()->id,
                // What the form shows as chosen: LDAP for an account with a directory username, else Microsoft.
                'sign_in_method' => $user->signsInWithDirectory() ? User::SIGN_IN_LDAP : User::SIGN_IN_MICROSOFT,
                'ldap_username' => $user->signsInWithDirectory() ? $user->ldap_username : null,
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
            'name' => ['required', 'string', 'max:255', self::NAME_RULE],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->whereNull('deleted_at')],
            // The form does not ask for the company: it is the caller's own (see newUserCompany()). A request that still
            // names one is held to the same rules as before.
            'company_id' => ['nullable', 'string', 'exists:companies,company_orangehr_code'],
            // The status the form chose; a request without one makes an active account.
            'active' => ['nullable', 'boolean'],
            // How the account signs in: see directoryUsername(). A request without it records no directory username.
            'sign_in_method' => ['nullable', 'string', Rule::in($this->offeredSignInMethods())],
            'ldap_username' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._@\-]+$/'],
            'role_id' => ['required', 'array', 'min:1'],
            // Roles are soft-deleted: a deleted one must not be handed out (the user would be left without a usable role).
            'role_id.*' => [Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ], self::messages(), self::attributes());

        if ($validator->fails()) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => $validator->errors()->all()
            ], 422);
        }

        // Deleting a user is a soft delete and the row keeps its unique e-mail:
        // adding the same person again brings that row back instead of inserting.
        $existing = $this->user->withTrashed()->people()->where('email', $request->email)->first();

        $companySelected = filled($request->company_id)
            ? $this->company->where('company_orangehr_code', $request->company_id)->first()
            // An account that is brought back keeps the company it had; a new one gets the caller's.
            : $this->company->find($existing?->company_id ?? $this->newUserCompany($request->user()));
        if (!$companySelected) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => self::say('company_missing')
            ], 422);
        }

        // False when the form did not say (or the column is not there yet): no directory username is recorded.
        $directoryUsername = $this->directoryUsername($request);

        if ($denied = $this->denyCompanyOutOfScope($request, $companySelected->id)
            ?? ($existing ? $this->denyUnlessManageable($request, $existing) : null)
            ?? $this->denySuperAdminGrant($request)
            ?? $this->denyRolesOutOfScope($request)
            ?? $this->denyDirectoryUsernameTaken($directoryUsername, $existing?->id)
            ?? $this->denyUnknownDirectoryAccount($directoryUsername)) {
            return $denied;
        }

        try {
            $saved = null;
            DB::transaction(function () use ($request, $companySelected, $existing, $directoryUsername, &$saved) {
                $attributes = [
                    'name' => $request->name,
                    'company_id' => $companySelected->id,
                    // Accounts sign in through SSO; a random hash keeps the e-mail + password form unusable for them.
                    'password' => bcrypt(Str::random(40)),
                    'remember_token' => null,
                    // NOT NULL columns without a default.
                    'verify_key' => '',
                    'mark_user' => '',
                    'active' => $request->boolean('active', true) ? '1' : '0',
                ] + (User::directoryReady() ? ['ldap_username' => $directoryUsername ?: null] : []);

                if ($user = $existing) {
                    $user->restore();
                    $user->update($attributes);
                } else {
                    $user = $this->user->create($attributes + ['email' => $request->email]);
                }

                $user->roles()->sync($request->role_id);
                $saved = $user;
            });

            // A deleted account that is added again keeps its row: the trail says so.
            app(AuditLogger::class)->log(AuditLogger::USER_CREATED, $saved,
                new: $this->auditFacts($saved) + ($existing ? ['Restored' => 'Yes, it had been deleted'] : []), label: $saved->email);

            return response()->json(["message" => self::say('created')], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => self::say('save_failed')], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $assignable = $this->assignableRoleIds($request->user());

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', self::NAME_RULE],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            // Not asked for by the form any more: without it the company of the user stays what it is.
            'company_id' => ['nullable', 'string', 'exists:companies,company_orangehr_code'],
            // The status the form chose; without one it stays what it is.
            'active' => ['nullable', 'boolean'],
            // How the account signs in: see directoryUsername(). Without it the directory username stays what it is.
            'sign_in_method' => ['nullable', 'string', Rule::in($this->offeredSignInMethods())],
            'ldap_username' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._@\-]+$/'],
            // A caller with a limited set of roles may leave every box unticked: the roles of the
            // user that their form does not list stay. The resulting list is checked below.
            'role_id' => $assignable === null ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'role_id.*' => [Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ], self::messages(), self::attributes());

        if ($validator->fails()) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => $validator->errors()->all()
            ], 422);
        }

        // Cari user berdasarkan ID (people only: API clients are managed on the page API Tokens)
        $user = $this->user->people()->find($id);
        if (!$user) {
            return response()->json([
                'message' => self::say('update_missing'),
                'errors' => self::say('update_missing_text')
            ], 404);
        }

        if ($denied = $this->denyUnlessManageable($request, $user)
            ?? $this->denySuperAdminGrant($request, $user)
            ?? $this->denyRolesOutOfScope($request, $user)) {
            return $denied;
        }

        // As in status(): setting your own account to inactive would end your own access.
        $active = $request->filled('active') ? $request->boolean('active') : null;
        if ($active === false && (int) $user->id === (int) $request->user()->id) {
            return response()->json(['message' => self::say('invalid'), 'errors' => [self::say('own_deactivate')]], 422);
        }

        // The company that was named, else the one the user has.
        $companySelected = filled($request->company_id)
            ? $this->company->where('company_orangehr_code', $request->company_id)->first()
            : $this->company->find($user->company_id);
        if (!$companySelected) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => self::say('company_missing_update')
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
                'message' => self::say('invalid'),
                'errors' => [self::say('email_locked')],
            ], 403);
        }

        // The directory account is a way in as well: whoever points an account at another one can sign in as it.
        $directoryUsername = $this->directoryUsername($request);
        $changesDirectory = $directoryUsername !== false
            && strcasecmp((string) $directoryUsername, (string) $user->ldap_username) !== 0;
        if ($unmanaged->isNotEmpty() && $changesDirectory) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => [self::say('method_locked')],
            ], 403);
        }
        if ($changesDirectory && ($denied = $this->denyDirectoryUsernameTaken($directoryUsername, (int) $user->id)
            ?? $this->denyUnknownDirectoryAccount($directoryUsername))) {
            return $denied;
        }

        // The roles the user ends up with.
        $roleIds = collect((array) $request->role_id)->map(fn ($roleId) => (int) $roleId)->merge($unmanaged)->unique()->values();
        if ($roleIds->isEmpty()) {
            return response()->json(['message' => self::say('invalid'), 'errors' => [self::say('role_required')]], 422);
        }
        if ($denied = $this->denyLiftingLimit($request, $user, $roleIds)) {
            return $denied;
        }

        $before = $this->auditFacts($user);

        try {
            DB::transaction(function () use ($request, $user, $companySelected, $roleIds, $active, $directoryUsername, $changesDirectory) {
                // "Has signed in with Microsoft" was said of the address the account had: a new address has not.
                if (User::directoryReady() && strcasecmp((string) $request->email, (string) $user->email) !== 0) {
                    $user->microsoft_linked_at = null;
                }

                // Update user data
                $user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'company_id' => $companySelected->id,
                ] + ($active === null ? [] : ['active' => $active ? '1' : '0'])
                  // A "remember me" cookie must not bring an inactive account back in.
                  + ($active === false ? ['remember_token' => null] : [])
                  + ($directoryUsername === false ? [] : ['ldap_username' => $directoryUsername])
                  // From now on its password is the one of the directory: a password that was set for it here, and a
                  // browser that was remembered with it, must not stay a way in (or come back as one later).
                  + ($changesDirectory && is_string($directoryUsername)
                      ? ['password' => bcrypt(Str::random(40)), 'remember_token' => null]
                      : []));

                $user->roles()->sync($roleIds->all());
            });

            $after = $this->auditFacts($user->fresh());
            app(AuditLogger::class)->logChanges(AuditLogger::USER_UPDATED, $user, $before, $after, $user->email);

            return response()->json(["message" => self::say('updated')], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => self::say('save_failed')], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = User::people()->find($id);
            if ($refused = $this->refusal($request, $user, 'delete')) {
                return response()->json(['message' => $refused['message']], $refused['status']);
            }
            $this->deleteUser($user);

            return response()->json(['message' => self::say('deleted')], 200);
        } catch (Exception $e) {
            report($e);

            return response()->json(['message' => self::say('delete_failed')], 500);
        }
    }

    /** Sets an account to active or inactive. An inactive account cannot sign in, and a session it has open ends. */
    public function status(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['active' => ['required', 'boolean']], [], self::attributes());
        if ($validator->fails()) {
            return response()->json(['message' => self::say('invalid'), 'errors' => $validator->errors()->all()], 422);
        }

        $user = $this->user->people()->find($id);
        if ($refused = $this->refusal($request, $user, 'deactivate')) {
            return response()->json(['message' => $refused['message']], $refused['status']);
        }
        $active = $request->boolean('active');
        $this->setStatus($user, $active);

        return response()->json(['message' => self::say($active ? 'activated' : 'deactivated')], 200);
    }

    /**
     * Activates or deactivates the accounts ticked in the list, each as status() would; the ones refused (one's own, one
     * not managed, already so) are said with the reason, the others go on.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'active' => ['required', 'boolean'],
            'ids' => ['required', 'array', 'min:1', 'max:' . self::BULK_MAX],
            'ids.*' => ['integer'],
        ], self::bulkMessages(), self::attributes());
        if ($validator->fails()) {
            return response()->json(['message' => self::say('invalid'), 'errors' => $validator->errors()->all()], 422);
        }
        $active = $request->boolean('active');

        return $this->bulk($request, 'deactivate', $active ? 'activated' : 'deactivated', function (User $user) use ($active) {
            if ($user->isActive() === $active) {
                return self::say($active ? 'already_active' : 'already_inactive');
            }
            $this->setStatus($user, $active);

            return null;
        });
    }

    /** Deletes the accounts ticked in the list, each as destroy() would. The person types how many, as a last look. */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:' . self::BULK_MAX],
            'ids.*' => ['integer'],
        ], self::bulkMessages(), self::attributes());
        if ($validator->fails()) {
            return response()->json(['message' => self::say('invalid'), 'errors' => $validator->errors()->all()], 422);
        }
        $count = count(array_unique(array_map('intval', $request->input('ids'))));
        if ((string) $request->input('confirm') !== (string) $count) {
            return response()->json(['message' => self::say('invalid'), 'errors' => [self::say('type_count', ['count' => $count])]], 422);
        }

        return $this->bulk($request, 'delete', 'deleted', function (User $user) {
            $this->deleteUser($user);

            return null;
        });
    }

    /** What is said when no account, or too many, were ticked. */
    private static function bulkMessages(): array
    {
        return ['ids.required' => self::say('none_selected'), 'ids.max' => self::say('too_many', ['max' => self::BULK_MAX])];
    }

    /**
     * Runs $one for each account ticked (null when it was done, else the reason it was not), after the refusals of the
     * single actions: not found, one's own, one the caller does not manage.
     *
     * @param Closure(User): ?string $one
     */
    private function bulk(Request $request, string $what, string $did, Closure $one): JsonResponse
    {
        $ids = array_values(array_unique(array_map('intval', $request->input('ids'))));
        $users = User::people()->with('roles')->whereIn('id', $ids)->get()->keyBy('id');
        $done = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $user = $users->get($id);
            $reason = $this->refusal($request, $user, $what)['message'] ?? null;
            if ($reason === null) {
                try {
                    $reason = $one($user);
                } catch (Exception $e) {
                    report($e);
                    $reason = self::say('bulk_error');
                }
            }
            if ($reason === null) {
                $done++;
            } else {
                $skipped[] = ['user' => $user?->email ?? "#{$id}", 'reason' => rtrim($reason, '.')];
            }
        }

        $message = trans_choice('UserManagement::users.messages.bulk_done.' . $did, $done, ['count' => $done])
            . ($skipped === [] ? '' : ' ' . self::say('bulk_skipped', ['count' => count($skipped)]));

        return response()->json(['message' => $message, 'done' => $done, 'skipped' => $skipped]);
    }

    /**
     * Why the caller may not deactivate or delete $user, as the single actions answer it; null when they may.
     *
     * @return array{status: int, message: string}|null
     */
    private function refusal(Request $request, ?User $user, string $what): ?array
    {
        if (! $user) {
            return ['status' => 404, 'message' => self::say('missing')];
        }
        // Doing it to yourself would end your own access, and may leave the application without an admin.
        if ((int) $user->id === (int) $request->user()->id) {
            return ['status' => 422, 'message' => self::say($what === 'delete' ? 'own_delete' : 'own_deactivate')];
        }
        if (! app(UserScope::class)->mayManage($request->user(), $user)) {
            return ['status' => 403, 'message' => self::say('not_allowed')];
        }

        return null;
    }

    private function setStatus(User $user, bool $active): void
    {
        $before = ['Status' => $user->isActive() ? 'Active' : 'Inactive'];
        // A "remember me" cookie of the account must not bring it back in.
        $user->update(['active' => $active ? '1' : '0'] + ($active ? [] : ['remember_token' => null]));
        app(AuditLogger::class)->logChanges(AuditLogger::USER_STATUS_CHANGED, $user, $before, ['Status' => $active ? 'Active' : 'Inactive'], $user->email);
    }

    private function deleteUser(User $user): void
    {
        $before = $this->auditFacts($user);
        DB::transaction(function () use ($user) {
            // Its roles go with it.
            $user->roles()->detach();
            $user->delete();
        });
        app(AuditLogger::class)->log(AuditLogger::USER_DELETED, $user, old: $before, label: $user->email);
    }

    /**
     * Gives an account a password for the e-mail + password form of the login page (an account is made with a random
     * one, for Microsoft sign-in only). The password is the caller's to pass on; it is not shown or sent anywhere.
     */
    public function password(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], self::attributes());
        if ($validator->fails()) {
            return response()->json(['message' => self::say('invalid'), 'errors' => $validator->errors()->all()], 422);
        }

        $user = $this->user->people()->with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => self::say('missing')], 404);
        }
        if ($denied = $this->denyUnlessManageable($request, $user)) {
            return $denied;
        }

        if ($user->signsInWithDirectory()) {
            return response()->json(['message' => self::say('directory_only')], 422);
        }

        // Whoever sets the password of an account can sign in as it, and so use the roles it holds:
        // the same rule as for changing its e-mail (see update()).
        $assignable = $this->assignableRoleIds($request->user());
        if ($assignable !== null && $user->roles->pluck('id')->map(fn ($roleId) => (int) $roleId)->diff($assignable)->isNotEmpty()) {
            return response()->json(['message' => self::say('password_locked')], 403);
        }

        $user->update(['password' => bcrypt($request->password), 'remember_token' => null]);
        // That a password was set, by whom: never the password.
        app(AuditLogger::class)->log(AuditLogger::USER_PASSWORD_RESET, $user, label: $user->email);

        return response()->json(['message' => self::say('password_saved')], 200);
    }

    /**
     * An account as the audit trail shows it: the fields of the form, the roles by name. ($user->roles is not used: it
     * may have been loaded before the roles were changed.)
     *
     * @return array<string, string|null>
     */
    private function auditFacts(User $user): array
    {
        return [
            'Name' => $user->name,
            'Email' => $user->email,
            'Role' => $user->roles()->orderBy('name')->pluck('name')->implode(', '),
            'Status' => $user->isActive() ? 'Active' : 'Inactive',
        ] + (User::directoryReady() ? [
            'Sign-in method' => filled($user->ldap_username) ? User::SIGN_IN_LDAP : User::SIGN_IN_MICROSOFT,
            'Directory username' => $user->ldap_username,
        ] : []);
    }

    /**
     * Colleagues from the employee directory whose name or work address holds what was typed: the form of a user takes
     * a name and an address from one of them ("Find employee").
     */
    public function employees(Request $request)
    {
        // The names and addresses of everyone employed: only for who may add or change a user.
        if (! $this->mayChangeUsers($request->user())) {
            abort(403);
        }

        $directory = app(EmployeeDirectory::class);
        $typed = $request->query('q');
        $found = is_string($typed) ? $directory->search($typed) : [];

        return response()->json([
            'available' => $directory->isAvailable(),
            'data' => $found,
            // Nobody found, and nobody could be looked for, are two answers: the form says which (why, is in the log).
            'failed' => $directory->failed(),
        ]);
    }

    /**
     * People of Active Directory whose name, username or address holds what was typed: with LDAP chosen, the form of a user
     * takes the name, the address and the directory username from one of them ("Find employee"). Looked up as the service
     * account of the directory.
     */
    public function directory(Request $request)
    {
        // Who is in the directory: only for who may add or change a user.
        if (! $this->mayChangeUsers($request->user())) {
            abort(403);
        }

        $directory = app(LdapAuthenticator::class);
        $typed = $request->query('q');
        $typed = is_string($typed) ? trim($typed) : '';
        $answer = ['available' => $directory->isConfigured(), 'data' => [], 'more' => false, 'failed' => false];
        if (! $answer['available'] || mb_strlen($typed) < app(EmployeeDirectory::class)->minimum()) {
            return response()->json($answer);
        }

        try {
            $found = $directory->search(mb_substr($typed, 0, 100), max(1, (int) config('ldap.people_search_limit', 8)));
        } catch (DirectoryUnavailable) {
            // Nobody found, and nobody could be looked for, are two answers: the form says which (why, is in the log).
            return response()->json(['failed' => true] + $answer);
        }

        return response()->json(['data' => $found['people'], 'more' => $found['more']] + $answer);
    }

    /**
     * Where "Find employee" searches for each sign-in method, or null where it is not offered: with LDAP, Active Directory
     * (it has the directory username the account signs in with), else the employee directory of HR; with Microsoft, the
     * employee directory (it has the work address Microsoft signs people in with).
     *
     * @return array<string, ?string> method => "directory", "hr" or null
     */
    private function lookupSources(User $caller): array
    {
        if (! $this->mayChangeUsers($caller)) {
            return [User::SIGN_IN_LDAP => null, User::SIGN_IN_MICROSOFT => null];
        }
        $hr = app(EmployeeDirectory::class)->isAvailable() ? 'hr' : null;

        return [
            User::SIGN_IN_LDAP => app(LdapAuthenticator::class)->isConfigured() ? 'directory' : $hr,
            User::SIGN_IN_MICROSOFT => $hr,
        ];
    }

    /**
     * A directory username is checked against Active Directory before an account is saved with it: one the directory does
     * not have (a typing mistake, or a username that is not the part of the address before the @) would only show at the
     * first sign-in. Not where Active Directory is not set up (the form says so).
     *
     * @param string|null|false $username
     */
    private function denyUnknownDirectoryAccount($username)
    {
        $directory = app(LdapAuthenticator::class);
        if (! is_string($username) || $username === '' || ! $directory->isConfigured()) {
            return null;
        }

        try {
            $account = $directory->lookup($username);
        } catch (DirectoryUnavailable) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => [self::say('directory_down')],
            ], 503);
        }

        if ($account === null) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => [self::say('directory_unknown', ['username' => $username])],
            ], 422);
        }
        if ($account['disabled']) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => [self::say('directory_disabled', ['username' => $username])],
            ], 422);
        }

        return null;
    }

    /** Whether the caller may add a user or change one (Create or Update on this menu). */
    private function mayChangeUsers(User $caller): bool
    {
        if ($this->isSuperAdmin($caller)) {
            return true;
        }
        $menu = CheckMenuPermission::menuForRoute('user-management.user-registered.index');

        return $menu !== null && (
            $caller->hasMenuPermission($menu->id, CheckMenuPermission::PERMISSION_IDS['Create'])
            || $caller->hasMenuPermission($menu->id, CheckMenuPermission::PERMISSION_IDS['Update'])
        );
    }

    /**
     * The sign-in methods the form offers: LDAP and Microsoft, as in the form this one follows (trilingual-docon), once
     * the column of the directory username is there. The choice does not come and go with the settings of Active
     * Directory: where they are missing the form says so (ldapConfigured), and an account made for LDAP meanwhile signs
     * in with Microsoft (the login form answers its directory password with "cannot be reached").
     *
     * @return string[]
     */
    private function offeredSignInMethods(): array
    {
        return User::directoryReady()
            ? [User::SIGN_IN_LDAP, User::SIGN_IN_MICROSOFT]
            : [User::SIGN_IN_MICROSOFT];
    }

    /**
     * The directory username the form asks for. With LDAP: what was typed, or the part of the e-mail before the @
     * (in this directory the two agree; the field is for the accounts where they do not). With Microsoft: none (null).
     * Lower case, because the directory does not mind the case and one account must not be recorded twice.
     * False when the form did not say: what the account has stays.
     *
     * @return string|null|false
     */
    private function directoryUsername(Request $request)
    {
        if (! User::directoryReady() || ! $request->filled('sign_in_method')) {
            return false;
        }
        if ($request->sign_in_method !== User::SIGN_IN_LDAP) {
            return null;
        }
        $typed = trim((string) $request->ldap_username);

        return Str::lower($typed !== '' ? $typed : Str::before((string) $request->email, '@'));
    }

    /**
     * One directory account belongs to one account here: with two, "who signed in" would have two answers.
     *
     * @param string|null|false $username
     */
    private function denyDirectoryUsernameTaken($username, ?int $exceptId)
    {
        if (! is_string($username) || $username === '') {
            return null;
        }
        $taken = $this->user->newQuery()
            ->where('ldap_username', $username)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        return $taken ? response()->json([
            'message' => self::say('invalid'),
            'errors' => [self::say('directory_taken', ['username' => $username])],
        ], 422) : null;
    }

    /* Whose accounts the caller reaches: UserScope, which the API goes by too. */

    private function isSuperAdmin(User $user): bool
    {
        return app(UserScope::class)->isSuperAdmin($user);
    }

    private function scopedRoles(User $caller)
    {
        return app(UserScope::class)->scopedRoles($caller);
    }

    private function allowedCompanyIds(User $caller)
    {
        return app(UserScope::class)->allowedCompanyIds($caller);
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
        return app(UserScope::class)->mayManage($request->user(), $target)
            ? null
            : response()->json(['message' => self::say('not_allowed')], 403);
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
            return response()->json(['message' => self::say('company_not_allowed')], 403);
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
                'message' => self::say('invalid'),
                'errors' => [self::say('role_not_allowed')],
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
                'message' => self::say('invalid'),
                'errors' => [self::say('own_role')],
            ], 422);
        }

        $wasLimited = $target->roles->contains(fn ($role) => $role->func_role !== 'ALL');
        if (! $wasLimited) {
            return null;
        }
        $staysLimited = $this->role::query()->whereIn('id', $roleIds)->where('func_role', '!=', 'ALL')->exists();
        $keepsUserManagement = DB::table('menu_role')->where('menu_id', $this->menuId)->whereIn('role_id', $roleIds)->exists();

        return $staysLimited || ! $keepsUserManagement ? null : response()->json([
            'message' => self::say('invalid'),
            'errors' => [self::say('lifts_limit')],
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
                'message' => self::say('invalid'),
                'errors' => [self::say('super_admin_only')],
            ], 403);
        }

        if ($target && (int) $target->id === (int) $caller->id && $this->isSuperAdmin($caller) && !$grantsSuperAdmin) {
            return response()->json([
                'message' => self::say('invalid'),
                'errors' => [self::say('own_super_admin')],
            ], 422);
        }

        return null;
    }
}
