<?php

namespace Tests\Concerns;

use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
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
     * One request to the API. The guards forget who was signed in before: a test runs every request in one
     * application, and the token guard would otherwise keep the account of the previous token.
     */
    protected function api(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, $uri, $data, $headers + ($token === null ? [] : ['Authorization' => 'Bearer ' . $token]));
    }
}
