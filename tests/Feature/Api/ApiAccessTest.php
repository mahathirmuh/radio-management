<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Api\Models\ApiToken;
use Modules\Api\Providers\ApiServiceProvider;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * Who gets into the API (/api/v1) and what they may do there: a Bearer token, its account and that account's role.
 */
class ApiAccessTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    public function test_without_a_good_token_the_answer_is_401_in_json(): void
    {
        $this->api('GET', '/api/v1/me')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
        $this->api('GET', '/api/v1/me', token: 'mtiradio_not-a-token')->assertStatus(401);
        $this->api('GET', '/api/v1/radios', token: '1|' . str_repeat('x', 40))->assertStatus(401);

        // A script that asks for no particular format still gets JSON, not the login page.
        $this->app['auth']->forgetGuards();
        $plain = $this->call('GET', '/api/v1/me');
        $plain->assertStatus(401);
        $this->assertSame(['message' => 'Unauthenticated.'], $plain->json());
    }

    public function test_a_token_acts_as_its_account_and_says_so(): void
    {
        $viewer = $this->makeUser('viewer@test.local', 'Viewer Radio', 'Vina Viewer');
        $token = $this->tokenFor($viewer, write: false, days: 30, name: 'Laporan');
        $this->assertStringStartsWith('mtiradio_', explode('|', $token, 2)[1]);

        $me = $this->api('GET', '/api/v1/me', token: $token)->assertOk()->json('data');
        $this->assertSame(['person', 'Vina Viewer', 'viewer@test.local', ['Viewer Radio']], [$me['account']['kind'], $me['account']['name'], $me['account']['email'], $me['account']['roles']]);
        $this->assertSame(['Laporan', ['read']], [$me['token']['name'], $me['token']['abilities']]);
        $this->assertSame(now()->addDays(30)->toDateString(), substr($me['token']['expires_at'], 0, 10));

        // Only its hash is kept.
        $this->assertFalse(DB::table('personal_access_tokens')->where('token', explode('|', $token, 2)[1])->exists());

        // Where and when it was last used is kept with it.
        $kept = ApiToken::sole();
        $this->assertNotNull($kept->last_used_at);
        $this->assertSame('127.0.0.1', $kept->last_used_ip);
    }

    public function test_a_read_only_token_only_reads(): void
    {
        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio');
        $reader = $this->tokenFor($admin, write: false);

        $this->api('GET', '/api/v1/radios', token: $reader)->assertOk();
        $this->api('POST', '/api/v1/radios', ['serial_number' => '867TZB0001'], $reader)
            ->assertForbidden()->assertJsonPath('message', 'This token may only read.');
        $this->api('POST', '/api/v1/radio-types', ['name' => 'X'], $reader)->assertForbidden();
    }

    public function test_what_a_token_may_do_is_what_role_and_permission_say(): void
    {
        $viewer = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $admin = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $controller = $this->tokenFor($this->makeUser('doc.controller@test.local', 'Document Controller'));
        $radio = ['serial_number' => '867TZB0001', 'radio_type_id' => DB::table('radio_types')->value('id'), 'placement' => 'stok_it', 'condition' => 'layak'];

        // Viewer Radio reads the register, but may not add to it, whatever its token.
        $this->api('GET', '/api/v1/radios', token: $viewer)->assertOk();
        $this->api('POST', '/api/v1/radios', $radio, $viewer)->assertForbidden()
            ->assertJsonPath('message', 'The role of this account may not add on "Register Radio" (Create).');
        // Admin Radio may.
        $this->api('POST', '/api/v1/radios', $radio, $admin)->assertCreated();
        // The Document Controller has no access to the register at all, but to the dashboard.
        $this->api('GET', '/api/v1/radios', token: $controller)->assertForbidden()
            ->assertJsonPath('message', 'The role of this account has no access to "Register Radio".');
        $this->api('GET', '/api/v1/dashboard', token: $controller)->assertOk();

        // Taking a permission away on Role & Permission takes it away from the API at once.
        $menu = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('id');
        DB::table('menu_role_permission')->where('menu_id', $menu)->where('role_id', DB::table('roles')->where('name', 'Admin Radio')->value('id'))
            ->where('permission_id', CheckMenuPermission::PERMISSION_IDS['Create'])->delete();
        $this->api('POST', '/api/v1/radios', ['serial_number' => '867TZB0002'] + $radio, $admin)->assertForbidden();
    }

    public function test_an_endpoint_whose_menu_is_missing_is_closed(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $this->api('GET', '/api/v1/history', token: $admin)->assertOk();

        $menu = DB::table('menu')->where('route', 'radio-management.history.index')->value('id');
        DB::table('menu_role_permission')->where('menu_id', $menu)->delete();
        DB::table('menu_role')->where('menu_id', $menu)->delete();
        DB::table('menu')->where('id', $menu)->delete();
        Cache::forget('rbac_menu_route_key_map');

        // Not "default open" as the pages: closed, to everybody but Super Admin.
        $this->api('GET', '/api/v1/history', token: $admin)->assertForbidden()
            ->assertJsonPath('message', 'This part of the API is closed: its menu is not in this application.');
        $this->api('GET', '/api/v1/history', token: $this->tokenFor($this->superAdmin()))->assertOk();
    }

    public function test_a_token_stops_when_it_expires_is_revoked_or_its_account_goes(): void
    {
        $person = $this->makeUser('staf@test.local', 'Viewer Radio');
        $token = $this->tokenFor($person);
        $this->api('GET', '/api/v1/me', token: $token)->assertOk();

        // Expired.
        ApiToken::query()->update(['expires_at' => now()->subMinute()]);
        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(401);
        ApiToken::query()->update(['expires_at' => now()->addDay()]);
        $this->api('GET', '/api/v1/me', token: $token)->assertOk();

        // The account set to inactive in User Management.
        $person->update(['active' => '0']);
        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(401)->assertJsonPath('message', 'This account is inactive.');
        $person->update(['active' => '1']);

        // The account deleted.
        $person->delete();
        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(401);
        $person->restore();

        // Revoked.
        ApiToken::query()->delete();
        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(401);
    }

    public function test_the_session_of_the_pages_does_not_open_the_api(): void
    {
        // Signed in in the browser, no token: the API does not answer for that person.
        $this->actingAs($this->superAdmin());
        $this->api('GET', '/api/v1/me')->assertStatus(401);
    }

    public function test_before_its_migration_the_api_says_it_is_not_switched_on(): void
    {
        $token = $this->tokenFor($this->superAdmin());
        Schema::drop('personal_access_tokens');
        Cache::forget(ApiToken::READY_CACHE_KEY);

        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(503)
            ->assertJsonPath('message', 'The API is not switched on yet: its migration has not been run on this database.');
    }

    public function test_an_unknown_record_is_404_without_the_names_of_the_code(): void
    {
        $token = $this->tokenFor($this->superAdmin());
        $this->api('GET', '/api/v1/radios/999999', token: $token)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->api('GET', '/api/v1/radio-types/999999', token: $token)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
        $this->api('GET', '/api/v1/nothing-here', token: $token)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_a_token_has_60_requests_a_minute(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'));
        for ($i = 1; $i <= ApiServiceProvider::PER_TOKEN; $i++) {
            $this->api('GET', '/api/v1/me', token: $token)->assertOk();
        }
        $this->api('GET', '/api/v1/me', token: $token)->assertStatus(429)->assertHeader('Retry-After');

        // Another token has its own count.
        $this->api('GET', '/api/v1/me', token: $this->tokenFor($this->makeUser('other@test.local', 'Viewer Radio')))->assertOk();
    }

    public function test_other_sites_may_not_call_the_api_from_a_browser_unless_named(): void
    {
        $preflight = fn () => $this->call('OPTIONS', '/api/v1/radios', server: [
            'HTTP_ORIGIN' => 'https://dashboard.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNull($preflight()->headers->get('Access-Control-Allow-Origin'));

        config(['cors.allowed_origins' => ['https://dashboard.example.com']]);
        $this->assertSame('https://dashboard.example.com', $preflight()->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_an_api_client_never_signs_in_on_the_screen(): void
    {
        $client = $this->apiClient('Power BI', 'Viewer Radio');
        $this->assertTrue($client->isApiClient());
        $this->assertStringEndsWith('@api-client.invalid', $client->email);

        // Whatever is typed (its address, with a password that was somehow set) it is "not registered".
        $client->forceFill(['password' => bcrypt('secret-password')])->save();
        $this->post('/login', ['emailusername' => $client->email, 'password' => 'secret-password']);
        $this->assertGuest();

        // Its token works.
        $this->api('GET', '/api/v1/me', token: $this->tokenFor($client))->assertOk()
            ->assertJsonPath('data.account.kind', 'client')->assertJsonPath('data.account.email', null);
    }

    public function test_api_clients_are_not_people_of_user_management(): void
    {
        $client = $this->apiClient('Power BI', 'Viewer Radio');
        $this->actingAs($this->superAdmin());

        $listed = collect($this->getJson(route('user-management.user-registered.list'))->assertOk()->json('data'))->pluck('email');
        $this->assertNotContains($client->email, $listed);
        $this->getJson(route('user-management.user-registered.show', $client->id))->assertNotFound();
        $this->putJson(route('user-management.user-registered.status', $client->id), ['active' => false])->assertNotFound();
        $this->deleteJson(route('user-management.user-registered.destroy', $client->id))->assertNotFound();
        $this->assertTrue(User::whereKey($client->id)->exists());

        // Nor an account a signature can be registered for.
        $candidates = collect($this->getJson(route('radio-management.e-sign.list'))->assertOk()->json('candidates'))->pluck('id');
        $this->assertNotContains($client->id, $candidates);
    }
}
