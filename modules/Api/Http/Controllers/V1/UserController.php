<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Models\UserManagement\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Api\Http\Resources\UserResource;
use Modules\RadioManagement\Services\Like;
use Modules\UserManagement\Services\UserScope;

/**
 * The accounts of User Management, read only: accounts are made and changed on the page. The accounts of applications
 * (API clients) are not in it. A role limited to some companies sees the accounts of those companies only, as on the
 * page.
 *
 * @tags Users
 */
class UserController extends ApiController
{
    public function __construct(private UserScope $scope)
    {
    }

    /**
     * List users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            /** In the name and the e-mail. */
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            /** Holders of this role, by id. */
            'role' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(array_merge(['name', 'email'], User::signInReady() ? ['last_login_at'] : []))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $caller = $request->user();
        $allowed = $this->scope->allowedCompanyIds($caller);
        $search = trim((string) $request->input('search'));

        $users = User::query()->people()->with(['company', 'roles'])
            ->when($allowed !== null, fn ($query) => $query->whereIn('company_id', $allowed))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('active', '0'))
            ->when($request->input('status') === 'active', fn ($query) => $query->where(fn ($q) => $q->where('active', '!=', '0')->orWhereNull('active')))
            ->when($request->filled('role'), function ($query) use ($request, $caller) {
                // The Super Admin role is a filter for a Super Admin only, as on the page: for anybody else it finds nobody.
                $role = $request->integer('role');
                $role === 1 && ! $this->scope->isSuperAdmin($caller)
                    ? $query->whereRaw('1 = 0')
                    : $query->whereHas('roles', fn ($roles) => $roles->where('roles.id', $role));
            })
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => Like::any($q, ['name', 'email'], $search)))
            ->orderBy($request->input('sort', 'name'), $request->input('direction') === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    /**
     * Show a user
     */
    public function show(Request $request, int $user): UserResource
    {
        $found = User::query()->people()->with(['company', 'roles'])->find($user);
        // The accounts of the list: an account of a company the caller does not reach is as good as not there.
        $allowed = $this->scope->allowedCompanyIds($request->user());
        abort_if($found === null || ($allowed !== null && ! $allowed->contains($found->company_id)), 404);

        return new UserResource($found);
    }
}
