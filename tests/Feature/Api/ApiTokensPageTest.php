<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\ApiTokensMenuSeeder;
use Database\Seeders\AuditLogMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Api\Models\ApiToken;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * The page "API Tokens" (Settings): Super Admin manages the API clients and every token; a role that is given the
 * menu makes and revokes tokens of its own only. And the documentation of the API.
 */
class ApiTokensPageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;
    use UsesApi;

    protected bool $seed = true;

    private function viewerRoleId(): int
    {
        return (int) DB::table('roles')->where('name', 'Viewer Radio')->value('id');
    }

    /** Gives $role the menu "API Tokens" with these permissions. */
    private function grantMenu(int $roleId, array $permissions): void
    {
        $menu = (int) DB::table('menu')->where('route', ApiTokensMenuSeeder::ROUTE)->value('id');
        DB::table('menu_role')->insert(['menu_id' => $menu, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('menu_role_permission')->insert(['menu_id' => $menu, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS[$permission], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_the_menu_is_below_the_audit_log_and_super_admins_alone(): void
    {
        $menu = DB::table('menu')->where('route', ApiTokensMenuSeeder::ROUTE)->sole();
        $audit = DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->sole();
        $approvals = DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->sole();
        $this->assertSame(['API Tokens', 'Settings', 'key', null], [$menu->title, $menu->section, $menu->icon, $menu->parent_id]);
        // Right below "User Management" (wherever the pages above have moved it).
        $users = (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order');
        $this->assertSame([$users + 1, $users + 2, $users + 3], [(int) $audit->order, (int) $menu->order, (int) $approvals->order]);
        $this->assertSame([1], DB::table('menu_role')->where('menu_id', $menu->id)->pluck('role_id')->map(fn ($id) => (int) $id)->all());

        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('api-tokens.index'))->assertOk()->getContent();
        foreach (['API Tokens', 'id="tokenTable"', 'id="clientTable"', 'API clients', 'New token', 'New client', 'API documentation', url('/api/v1')] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        $this->assertStringNotContainsString('tokensNotReady', $page);

        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->get(route('api-tokens.index'))->assertForbidden();
        $this->getJson(route('api-tokens.list'))->assertForbidden();
    }

    public function test_super_admin_makes_a_client_and_a_token_that_is_shown_once(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $this->postJson(route('api-tokens.store-client'), ['name' => 'Power BI', 'role_id' => 1])->assertStatus(422);
        $this->postJson(route('api-tokens.store-client'), ['name' => 'Power BI', 'role_id' => $this->viewerRoleId()])->assertCreated();
        $client = User::where('api_client', true)->sole();
        $this->assertSame(['Power BI', ['Viewer Radio'], true], [$client->name, $client->roles->pluck('name')->all(), $client->isActive()]);

        $made = $this->postJson(route('api-tokens.store-token'), ['name' => 'Laporan bulanan', 'owner' => (string) $client->id, 'access' => 'read', 'days' => 90])
            ->assertCreated()->json();
        $this->assertMatchesRegularExpression('/^\d+\|mtiradio_\w{40,}$/', $made['token']);
        $this->assertSame(['Read only', false, now()->addDays(90)->format('d-m-Y')], [$made['data']['access'], $made['data']['writes'], $made['data']['expires_at']]);

        // The list shows it, never its secret.
        $listed = $this->getJson(route('api-tokens.list'))->assertOk()->json();
        $this->assertSame(['Laporan bulanan'], array_column($listed['tokens'], 'name'));
        $this->assertSame(['client', 'Power BI'], [$listed['tokens'][0]['owner']['kind'], $listed['tokens'][0]['owner']['name']]);
        $this->assertSame([['Power BI', 1]], array_map(fn ($c) => [$c['name'], $c['tokens']], $listed['clients']));
        $this->assertStringNotContainsString(explode('|', $made['token'])[1], json_encode($listed));

        // It works, as its client, read only.
        $this->api('GET', '/api/v1/me', token: $made['token'])->assertOk()->assertJsonPath('data.account.name', 'Power BI');

        // The audit trail names the token, never holds it.
        $this->assertSame(['api.client_created', 'api.token_created'], AuditLog::where('action', 'like', 'api.%')->orderBy('id')->pluck('action')->all());
        $this->assertStringNotContainsString(explode('|', $made['token'])[1], (string) json_encode(DB::table('audit_logs')->get()));
    }

    public function test_an_inactive_or_deleted_client_stops_its_tokens(): void
    {
        $this->actingAs($this->superAdmin());
        $client = $this->apiClient('Power BI', 'Viewer Radio');
        $token = $this->tokenFor($client);
        $this->api('GET', '/api/v1/me', token: $token)->assertOk();

        $this->actingAs($this->superAdmin());
        $this->putJson(route('api-tokens.update-client', $client->id), ['name' => 'Power BI', 'role_id' => $this->viewerRoleId(), 'active' => false])->assertOk();
        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(401);
        // No new token for an inactive client.
        $this->actingAs($this->superAdmin());
        $this->postJson(route('api-tokens.store-token'), ['name' => 'x', 'owner' => (string) $client->id, 'access' => 'read', 'days' => 30])->assertStatus(422);

        $this->deleteJson(route('api-tokens.destroy-client', $client->id))->assertOk();
        $this->assertSoftDeleted('users', ['id' => $client->id]);
        $this->assertSame(0, ApiToken::count());
        $this->assertSame(['api.client_created', 'api.token_created', 'api.client_updated', 'api.client_deleted'],
            AuditLog::where('action', 'like', 'api.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_a_revoked_token_stops_at_once(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $made = $this->postJson(route('api-tokens.store-token'), ['name' => 'Script', 'owner' => 'me', 'access' => 'read_write', 'days' => 30])->assertCreated()->json();
        $this->api('GET', '/api/v1/me', token: $made['token'])->assertOk();

        $this->actingAs($admin);
        $this->deleteJson(route('api-tokens.destroy-token', $made['data']['id']))->assertOk();
        $this->api('GET', '/api/v1/me', token: $made['token'])->assertStatus(401);
        $this->assertSame(AuditLogger::API_TOKEN_REVOKED, AuditLog::orderByDesc('id')->first()->action);
    }

    public function test_another_role_with_the_menu_has_tokens_of_its_own_only(): void
    {
        $this->grantMenu($this->viewerRoleId(), ['Read', 'Create', 'Delete']);
        $viewer = $this->makeUser('viewer@test.local', 'Viewer Radio', 'Vina');
        $other = $this->makeUser('other@test.local', 'Viewer Radio', 'Oki');
        $client = $this->apiClient('Power BI', 'Viewer Radio');
        $othersToken = ApiToken::query()->findOrFail((int) explode('|', $this->tokenFor($other))[0]);
        $this->tokenFor($client);

        $this->actingAs($viewer);
        $page = (string) $this->get(route('api-tokens.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="clientTable"', $page);

        $made = $this->postJson(route('api-tokens.store-token'), ['name' => 'Mine', 'owner' => 'me', 'access' => 'read', 'days' => 30])->assertCreated()->json();
        $this->assertSame(['Mine'], array_column($this->getJson(route('api-tokens.list'))->json('tokens'), 'name'));
        $this->assertSame([], $this->getJson(route('api-tokens.list'))->json('clients'));

        // Not for a client, not somebody else's, no clients at all.
        $this->postJson(route('api-tokens.store-token'), ['name' => 'x', 'owner' => (string) $client->id, 'access' => 'read', 'days' => 30])->assertForbidden();
        $this->deleteJson(route('api-tokens.destroy-token', $othersToken->id))->assertNotFound();
        $this->postJson(route('api-tokens.store-client'), ['name' => 'X', 'role_id' => $this->viewerRoleId()])->assertForbidden();
        $this->deleteJson(route('api-tokens.destroy-token', $made['data']['id']))->assertOk();
        $this->assertTrue(ApiToken::whereKey($othersToken->id)->exists());

        // Without Create on the menu, no token.
        DB::table('menu_role_permission')->where('role_id', $this->viewerRoleId())->where('permission_id', CheckMenuPermission::PERMISSION_IDS['Create'])
            ->where('menu_id', DB::table('menu')->where('route', ApiTokensMenuSeeder::ROUTE)->value('id'))->delete();
        $this->postJson(route('api-tokens.store-token'), ['name' => 'Again', 'owner' => 'me', 'access' => 'read', 'days' => 30])->assertForbidden();
    }

    public function test_the_documentation_is_for_who_may_hold_a_token(): void
    {
        $this->get('/docs/api')->assertRedirect(route('login'));

        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->get('/docs/api')->assertForbidden();

        $this->actingAs($this->superAdmin());
        $this->get('/docs/api')->assertOk()->assertSee('MTI Radio Management API');
        $spec = $this->get('/docs/api.json')->assertOk()->json();
        $this->assertArrayHasKey('/radios', $spec['paths']);
        $this->assertArrayHasKey('/radios/{radio}/archive', $spec['paths']);
        foreach (['/radios/export', '/radios/{radio}/checkout-form', '/import', '/users', '/users/{user}', '/roles', '/roles/{role}', '/audit-log',
            '/documents/cof-scan', '/documents/cof-scan/files/{id}', '/documents/ict-masterlist', '/documents/ict-masterlist/rows',
            '/documents/ict-masterlist/edit-requests', '/documents/edit-requests', '/documents/edit-requests/{editRequest}',
            '/documents/edit-requests/{editRequest}/approve', '/documents/edit-requests/{editRequest}/reject',
            '/e-signs', '/e-signs/{signature}/uses', '/e-signs/{signature}/deactivate'] as $path) {
            $this->assertArrayHasKey($path, $spec['paths'], $path);
        }
        // The folder of a page is no parameter of its address: only the file, and the folder below it is in.
        $this->assertSame(['id', 'path'], array_column($spec['paths']['/documents/cof-scan/files/{id}']['get']['parameters'] ?? [], 'name'));
        // Asking for a change takes the cell and its value.
        $this->assertSame(['sheet', 'line', 'column', 'value'], array_keys($spec['paths']['/documents/ict-masterlist/edit-requests']['post']['requestBody']['content']['application/json']['schema']['properties'] ?? []));
        // The import takes the file of the page's form.
        $this->assertArrayHasKey('file', $spec['paths']['/import']['post']['requestBody']['content']['multipart/form-data']['schema']['properties'] ?? []);
        $this->assertArrayNotHasKey('/approval-management/flows', $spec['paths']);
        $this->assertSame(['http', 'bearer'], [$spec['components']['securitySchemes']['token']['type'] ?? null, $spec['components']['securitySchemes']['token']['scheme'] ?? null]);
    }

    public function test_before_its_migrations_the_page_says_so(): void
    {
        Schema::drop('personal_access_tokens');
        Cache::forget(ApiToken::READY_CACHE_KEY);
        $this->actingAs($this->superAdmin());

        $this->assertStringContainsString('id="tokensNotReady"', (string) $this->get(route('api-tokens.index'))->assertOk()->getContent());
        $this->getJson(route('api-tokens.list'))->assertStatus(503);
        $this->postJson(route('api-tokens.store-token'), ['name' => 'x', 'owner' => 'me', 'access' => 'read', 'days' => 30])->assertStatus(503);
    }

    public function test_the_script_of_the_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('api-tokens.index'), 'tests/tools/api-tokens-behaviour.mjs', 9);
    }
}
