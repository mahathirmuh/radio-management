<?php

namespace Modules\Api\Services;

use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Modules\Api\Models\ApiToken;

/**
 * The API clients (accounts of applications) and the tokens of the API: made, changed, revoked, and written into the
 * audit trail. A token is shown once, when it is made; the trail names it, never holds it.
 */
class ApiTokens
{
    /** The domain of the address an API client gets: reserved (RFC 2606), so no person and no Microsoft account has it. */
    public const CLIENT_DOMAIN = 'api-client.invalid';

    /** A new account of an application, with one role (never Super Admin's). */
    public function createClient(User $by, string $name, Role $role): User
    {
        $client = DB::transaction(function () use ($by, $name, $role) {
            $client = User::create([
                'name' => $name,
                'email' => 'client-' . Str::lower(Str::random(16)) . '@' . self::CLIENT_DOMAIN,
                'company_id' => $by->company_id,
                // Never used: an API client does not sign in on the screen (LoginController refuses it whatever it types).
                'password' => bcrypt(Str::random(40)),
                // NOT NULL columns without a default.
                'verify_key' => '',
                'mark_user' => '',
                'active' => '1',
            ]);
            // Not fillable on purpose: no form of User Management can make an account into one, or back.
            $client->forceFill(['api_client' => true])->save();
            $client->roles()->sync([$role->id]);

            return $client;
        });
        app(AuditLogger::class)->log(AuditLogger::API_CLIENT_CREATED, $client, new: $this->clientFacts($client), label: $client->name);

        return $client;
    }

    public function updateClient(User $client, string $name, Role $role, bool $active): User
    {
        $before = $this->clientFacts($client);
        DB::transaction(function () use ($client, $name, $role, $active) {
            $client->update(['name' => $name, 'active' => $active ? '1' : '0']);
            $client->roles()->sync([$role->id]);
        });
        app(AuditLogger::class)->logChanges(AuditLogger::API_CLIENT_UPDATED, $client, $before, $this->clientFacts($client->fresh()), $client->name);

        return $client;
    }

    /** Deletes the client and revokes its tokens. Its name stays in the history of what it did. */
    public function deleteClient(User $client): void
    {
        $before = $this->clientFacts($client);
        DB::transaction(function () use ($client) {
            $client->tokens()->delete();
            $client->roles()->detach();
            $client->delete();
        });
        app(AuditLogger::class)->log(AuditLogger::API_CLIENT_DELETED, $client, old: $before, label: $client->name);
    }

    /** A token for $owner (a person, for themself, or an API client): read only, or read and write; it ends after $days. */
    public function createToken(User $owner, string $name, bool $write, int $days): NewAccessToken
    {
        // At the end of its last day, to the second: SQL Server would round 23:59:59.999999 up to the next day.
        $made = $owner->createToken($name, $write ? [ApiToken::READ, ApiToken::WRITE] : [ApiToken::READ], now()->addDays($days)->setTime(23, 59, 59));
        app(AuditLogger::class)->log(AuditLogger::API_TOKEN_CREATED, $made->accessToken, new: $this->tokenFacts($made->accessToken, $owner), label: $this->tokenLabel($made->accessToken, $owner));

        return $made;
    }

    public function revoke(ApiToken $token): void
    {
        $owner = $token->tokenable()->withTrashed()->first();
        $before = $this->tokenFacts($token, $owner);
        $label = $this->tokenLabel($token, $owner);
        $token->delete();
        app(AuditLogger::class)->log(AuditLogger::API_TOKEN_REVOKED, $token, old: $before, label: $label);
    }

    /** @return array<string, string|null> */
    private function clientFacts(User $client): array
    {
        return [
            'Name' => $client->name,
            'Role' => $client->roles()->orderBy('name')->pluck('name')->implode(', '),
            'Status' => $client->isActive() ? 'Active' : 'Inactive',
        ];
    }

    /** @return array<string, string|null> */
    private function tokenFacts(ApiToken $token, ?User $owner): array
    {
        return [
            'Name' => $token->name,
            'Owner' => $owner === null ? null : ($owner->isApiClient() ? $owner->name . ' (API client)' : $owner->email),
            'Access' => $token->accessLabel(),
            'Expires' => $token->expires_at?->format('d-m-Y'),
        ];
    }

    private function tokenLabel(ApiToken $token, ?User $owner): string
    {
        return $token->name . ' · ' . ($owner?->name ?? 'deleted account');
    }
}
