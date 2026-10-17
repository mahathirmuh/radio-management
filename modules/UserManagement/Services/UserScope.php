<?php

namespace Modules\UserManagement\Services;

use App\Models\Organization\Company;
use App\Models\UserManagement\User;
use Illuminate\Support\Collection;

/**
 * Whose accounts a caller reaches in User Management: everyone, or (with a role limited to some companies: func_role
 * HO / SITE / COMPANY) the accounts of those companies only, never a Super Admin. The page and the API go by it.
 */
class UserScope
{
    public function isSuperAdmin(User $user): bool
    {
        return $user->roles->contains('id', 1);
    }

    /** The caller's roles that are limited to some companies (func_role HO / SITE / COMPANY). */
    public function scopedRoles(User $caller): Collection
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
    public function allowedCompanyIds(User $caller): ?Collection
    {
        $scoped = $this->scopedRoles($caller);
        if ($scoped->isEmpty()) {
            return null;
        }
        $codes = $scoped->flatMap(fn ($role) => (array) ($caller->haveCompanyOrangeHr($role) ?? []))->unique()->values();

        return Company::whereIn('company_orangehr_code', $codes)->pluck('id');
    }

    /** Whether the caller may see and manage this account. */
    public function mayManage(User $caller, User $target): bool
    {
        if ($this->isSuperAdmin($caller)) {
            return true;
        }
        $allowed = $this->allowedCompanyIds($caller);

        return ! $this->isSuperAdmin($target) && ($allowed === null || $allowed->contains($target->company_id));
    }
}
