<?php

namespace Tests\Concerns;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Api\Services\ApiTokens;

/**
 * Calling the API as an application does: with a real token from the page's service, in its Authorization header.
 */
trait UsesApi
{
    /** A token of $owner, as the page "API Tokens" makes it; the plain text, shown once. */
    protected function tokenFor(User $owner, bool $write = true, int $days = 365, string $name = 'Test token'): string
    {
        return app(ApiTokens::class)->createToken($owner, $name, $write, $days)->plainTextToken;
    }

    /** An API client (an application's account) with $role. */
    protected function apiClient(string $name, string $role): User
    {
        return app(ApiTokens::class)->createClient($this->superAdmin(), $name, Role::where('name', $role)->firstOrFail());
    }

    /**
     * A role of its own with these menus (route keys without ".index") and these permissions on each, as Role &
     * Permission saves it.
     *
     * @param array<string, string[]> $grants menu key => ['Read', 'Create', ...]
     */
    protected function roleWith(array $grants, string $name = 'API test role'): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $name . ' ' . uniqid(), 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($grants as $menuKey => $permissions) {
            $menuId = DB::table('menu')->where('route', $menuKey . '.index')->value('id');
            $this->assertNotNull($menuId, "No menu for {$menuKey}");
            DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($permissions as $permission) {
                DB::table('menu_role_permission')->insert([
                    'menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS[$permission],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return (int) $roleId;
    }

    /**
     * One request to the API. The guards forget who was signed in before: a test runs every request in one
     * application, and the token guard would otherwise keep the account of the previous token.
     */
    protected function api(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, $uri, $data, $headers + ($token === null ? [] : ['Authorization' => 'Bearer ' . $token]));
    }

    /** A multipart request to the API, as an application sends a file. */
    protected function apiUpload(string $uri, array $data, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders(['Accept' => 'application/json'] + ($token === null ? [] : ['Authorization' => 'Bearer ' . $token]))
            ->post($uri, $data);
        // The headers stay on the test case otherwise, token and all, for the requests after.
        $this->flushHeaders();

        return $response;
    }
}
