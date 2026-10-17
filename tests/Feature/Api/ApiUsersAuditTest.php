<?php

namespace Tests\Feature\Api;

use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Models\RadioType;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * User Management, Role & Permission and the Audit Log through the API: read only, and seen as on their pages.
 */
class ApiUsersAuditTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    private function roleId(string $name): int
    {
        return (int) DB::table('roles')->where('name', $name)->value('id');
    }

    public function test_super_admin_reads_the_people_of_user_management_never_their_secrets(): void
    {
        $admin = $this->superAdmin();
        $viewer = $this->makeUser('vina@test.local', 'Viewer Radio', 'Vina Viewer');
        $viewer->forceFill(['ldap_username' => 'vina.viewer'])->save();
        $this->makeUser('oki@test.local', 'Admin Radio', 'Oki Admin')->update(['active' => '0']);
        $client = $this->apiClient('Power BI', 'Viewer Radio');
        $token = $this->tokenFor($admin, write: false);

        $all = $this->api('GET', '/api/v1/users', token: $token)->assertOk()->json();
        $emails = array_column($all['data'], 'email');
        $this->assertEqualsCanonicalizing([$admin->email, 'vina@test.local', 'oki@test.local'], $emails);
        $this->assertNotContains($client->email, $emails);
        foreach (['password', 'remember_token', 'verify_key', 'access_token', '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($all), $secret);
        }

        $vina = collect($all['data'])->firstWhere('email', 'vina@test.local');
        $this->assertSame(['Vina Viewer', [['id' => $this->roleId('Viewer Radio'), 'name' => 'Viewer Radio']], true, 'LDAP', 'vina.viewer', 'MCG'],
            [$vina['name'], $vina['roles'], $vina['active'], $vina['sign_in_method'], $vina['directory_username'], $vina['company']['code']]);

        $this->assertSame(['oki@test.local'], array_column($this->api('GET', '/api/v1/users?status=inactive', token: $token)->json('data'), 'email'));
        $this->assertSame(['vina@test.local'], array_column($this->api('GET', '/api/v1/users?search=Vina', token: $token)->json('data'), 'email'));
        $this->assertSame([$admin->email], array_column($this->api('GET', '/api/v1/users?role=1', token: $token)->json('data'), 'email'));

        $this->api('GET', "/api/v1/users/{$viewer->id}", token: $token)->assertOk()->assertJsonPath('data.email', 'vina@test.local');
        // An API client is not a user of User Management.
        $this->api('GET', "/api/v1/users/{$client->id}", token: $token)->assertNotFound();
        // Nothing is changed through the API.
        $this->api('POST', '/api/v1/users', ['name' => 'X', 'email' => 'x@test.local'], $this->tokenFor($admin))->assertStatus(405);
    }

    public function test_users_are_read_with_the_menu_and_the_reach_of_the_page(): void
    {
        // Admin Radio has no User Management.
        $this->api('GET', '/api/v1/users', token: $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio')))->assertForbidden();

        $reader = $this->makeUser('reader@test.local', $this->roleWith(['user-management.user-registered' => ['Read']]), 'Rina Reader');
        $token = $this->tokenFor($reader, write: false);
        $admin = $this->superAdmin();
        // The role Super Admin is a filter for a Super Admin only.
        $this->assertSame([], $this->api('GET', '/api/v1/users?role=1', token: $token)->json('data'));
        $this->api('GET', "/api/v1/users/{$admin->id}", token: $token)->assertOk();

        // Limited to one company (3): the accounts of the others are not there.
        DB::table('roles')->where('id', $reader->roles->first()->id)->update(['func_role' => 'COMPANY', 'setting_role' => json_encode(['3'])]);
        $outside = $this->makeUser('outside@test.local', 'Viewer Radio');
        DB::table('users')->where('id', $outside->id)->update(['company_id' => (int) DB::table('companies')->where('company_orangehr_code', 'MTI')->value('id')]);
        $token = $this->tokenFor($reader->fresh(), write: false);
        $emails = array_column($this->api('GET', '/api/v1/users', token: $token)->json('data'), 'email');
        $this->assertContains('reader@test.local', $emails);
        $this->assertNotContains('outside@test.local', $emails);
        $this->api('GET', "/api/v1/users/{$outside->id}", token: $token)->assertNotFound();
    }

    public function test_roles_are_read_with_what_they_may_do(): void
    {
        $token = $this->tokenFor($this->superAdmin(), write: false);
        $this->makeUser('vina@test.local', 'Viewer Radio');
        $this->apiClient('Power BI', 'Viewer Radio');

        $roles = collect($this->api('GET', '/api/v1/roles?per_page=100', token: $token)->assertOk()->json('data'))->keyBy('name');
        $this->assertTrue($roles->has('Super Admin'));
        $this->assertSame([1, 'ALL'], [$roles['Viewer Radio']['people_count'], $roles['Viewer Radio']['scope']]);
        // The home page is named by its menu, not by the number the table keeps.
        $home = (int) DB::table('roles')->where('name', 'Viewer Radio')->value('home_page');
        $this->assertSame([$home, DB::table('menu')->where('id', $home)->value('title')], [$roles['Viewer Radio']['home_page']['id'], $roles['Viewer Radio']['home_page']['title']]);

        $viewer = $this->api('GET', '/api/v1/roles/' . $this->roleId('Viewer Radio'), token: $token)->assertOk()->json('data');
        $register = collect($viewer['menus'])->firstWhere('route', 'radio-management.radio.index');
        $this->assertSame(['Register Radio', ['Read']], [$register['title'], $register['permissions']]);
        $admin = $this->api('GET', '/api/v1/roles/' . $this->roleId('Admin Radio'), token: $token)->json('data');
        // In the order of the permissions' numbers, as the audit trail writes them.
        $this->assertSame(['Create', 'Read', 'Update', 'Delete'], collect($admin['menus'])->firstWhere('route', 'radio-management.radio.index')['permissions']);

        // Somebody who is not Super Admin does not see that role.
        $reader = $this->tokenFor($this->makeUser('reader@test.local', $this->roleWith(['user-management.role-permission' => ['Read']])), write: false);
        $this->assertNotContains('Super Admin', array_column($this->api('GET', '/api/v1/roles?per_page=100', token: $reader)->json('data'), 'name'));
        $this->api('GET', '/api/v1/roles/1', token: $reader)->assertNotFound();
        $this->api('GET', '/api/v1/roles', token: $this->tokenFor($this->makeUser('viewer2@test.local', 'Viewer Radio')))->assertForbidden();
    }

    public function test_the_audit_log_is_read_with_its_filters(): void
    {
        // First, before any token: within one test the token guard stays the default one, and the login form would
        // take the request for a signed-in one.
        $this->post('/login', ['emailusername' => 'siapa@test.local', 'password' => 'Salah-123']);
        $this->assertGuest();
        $admin = $this->superAdmin();
        $writer = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'), name: 'Inventaris');
        $made = $this->api('POST', '/api/v1/radios', [
            'dmr_id' => 2001, 'serial_number' => '867TZB9999', 'radio_type_id' => RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id'),
            'user_name' => 'Budi Santoso', 'placement' => 'di_user', 'condition' => 'layak',
        ], $writer)->assertCreated()->json('data.id');
        $this->api('PUT', "/api/v1/radios/{$made}", ['user_name' => 'Andi', 'placement' => 'di_user', 'condition' => 'layak'], $writer)->assertOk();
        $token = $this->tokenFor($admin, write: false);

        $all = $this->api('GET', '/api/v1/audit-log', token: $token)->assertOk()->json();
        $this->assertGreaterThanOrEqual(3, $all['meta']['total']);

        $changed = $this->api('GET', '/api/v1/audit-log?action=radio.updated', token: $token)->json('data');
        $this->assertCount(1, $changed);
        $this->assertSame(['Radio', $made, ['User/Unit' => 'Budi Santoso'], ['User/Unit' => 'Andi'], 'admin.radio@test.local'],
            [$changed[0]['entity']['type'], $changed[0]['entity']['id'], $changed[0]['old_values'], $changed[0]['new_values'], $changed[0]['user']['email']]);
        $this->assertMatchesRegularExpression('/^API · Inventaris \(#\d+\)$/', $changed[0]['via']);
        $this->assertSame('updated radio', $changed[0]['activity']);

        // The whole trail of one radio; a failed sign-in by what was typed; by the day.
        $this->assertSame(['radio.updated', 'radio.created'], array_column($this->api('GET', "/api/v1/audit-log?entity_type=Radio&entity_id={$made}", token: $token)->json('data'), 'action'));
        $failed = $this->api('GET', '/api/v1/audit-log?search=siapa@test', token: $token)->json('data');
        $this->assertSame([AuditLogger::LOGIN_FAILED], array_column($failed, 'action'));
        $this->assertSame([null, 'Guest'], [$failed[0]['user']['id'], $failed[0]['user']['name']]);
        $this->assertStringNotContainsString('Salah-123', json_encode($all));
        $this->assertSame(0, $this->api('GET', '/api/v1/audit-log?date_to=' . now()->subDay()->toDateString(), token: $token)->json('meta.total'));
        $this->assertSame($all['meta']['total'], $this->api('GET', '/api/v1/audit-log?date_from=' . now()->toDateString() . '&date_to=' . now()->toDateString(), token: $token)->json('meta.total'));

        // Only with the menu; and before the migration, 503.
        $this->api('GET', '/api/v1/audit-log', token: $writer)->assertForbidden();
        Schema::drop('audit_logs');
        Cache::forget(AuditLog::READY_CACHE_KEY);
        $this->api('GET', '/api/v1/audit-log', token: $token)->assertStatus(503);
    }
}
