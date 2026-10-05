<?php

namespace Tests\Concerns;

use App\Models\UserManagement\User;
use Illuminate\Support\Facades\DB;

/**
 * The application seeds no accounts (they are made with `php artisan user:register`
 * or in User Management), so the tests bring their own.
 */
trait MakesUsers
{
    /** An account holding the Super Admin role (role id 1). */
    protected function superAdmin(): User
    {
        return User::where('email', 'superadmin@test.local')->first()
            ?? $this->makeUser('superadmin@test.local', 1, 'Super Admin');
    }

    /**
     * @param int|string|null $role a role id, a role name, or null for an account without a role
     */
    protected function makeUser(string $email, int|string|null $role = null, string $name = 'Tester'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'company_id' => 3,
            'password' => bcrypt('secret-password'),
            // NOT NULL columns without a default.
            'verify_key' => '',
            'mark_user' => '',
            'active' => '1',
        ]);

        if ($role !== null) {
            $roleId = is_int($role) ? $role : DB::table('roles')->where('name', $role)->value('id');
            $this->assertNotNull($roleId, "Role \"{$role}\" does not exist.");
            $user->roles()->attach($roleId);
        }

        return $user->fresh();
    }
}
