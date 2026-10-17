<?php

namespace Modules\Api\Http\Resources;

use App\Models\UserManagement\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An account of User Management, as the API shows it: never its password, its tokens or its remember-me cookie.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $signIn = User::signInReady();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => $user->company === null ? null : ['code' => $user->company->company_orangehr_code, 'name' => $user->company->name],
            'roles' => $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values(),
            'active' => $user->isActive(),
            /** How the account gets in: "LDAP", "Microsoft", "Both" or "Pending". */
            'sign_in_method' => $user->signInMethod(),
            /** The Active Directory username, for an account that signs in with LDAP. */
            'directory_username' => $user->signsInWithDirectory() ? $user->ldap_username : null,
            'last_sign_in_at' => $signIn ? $user->last_login_at?->toIso8601String() : null,
            /** "Password", "LDAP" or "Microsoft". */
            'last_sign_in_method' => $signIn ? $user->last_login_method : null,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
