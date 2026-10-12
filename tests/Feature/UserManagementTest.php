<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return $this->superAdmin();
    }

    private function roleId(string $name): int
    {
        return (int) DB::table('roles')->where('name', $name)->value('id');
    }

    /** A role with full access to the given menus (route keys without ".index"). */
    private function role(string $name, array $menuKeys): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $name, 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($menuKeys as $menuKey) {
            $menuId = DB::table('menu')->where('route', $menuKey . '.index')->value('id');
            $this->assertNotNull($menuId, "No menu for {$menuKey}");
            DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
            foreach (CheckMenuPermission::PERMISSION_IDS as $permissionId) {
                DB::table('menu_role_permission')->insert([
                    'menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => $permissionId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $roleId;
    }

    private function user(string $email, int $roleId): User
    {
        return $this->makeUser($email, $roleId);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@merdekabattery.com',
            'position' => 'IT Field', 'company_id' => 'MTI',
            'role_id' => [$this->roleId('Viewer Radio')],
        ], $overrides);
    }

    public function test_the_form_asks_for_a_position_and_no_longer_for_the_company(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // The page: a position to choose (six of them), in the form, the filter and the table; nothing about the company.
        $page = (string) $this->get(route('user-management.user-registered.index'))->assertOk()->getContent();
        foreach (['id="positionSelect"', 'id="positionSelectFilter"'] as $part) {
            $this->assertSame(1, substr_count($page, $part), $part);
        }
        $this->assertSame(['Manager', 'Superintendent', 'Supervisor', 'IT Field', 'IT System Support', 'IT Assistant'], User::POSITIONS);
        $options = implode('\s*', array_map(fn (string $position) => '<option value="' . $position . '">' . $position . '</option>', User::POSITIONS));
        $this->assertSame(2, preg_match_all('#' . $options . '#', $page), 'in the form and in the filter, in that order');
        $this->assertMatchesRegularExpression('#<th [^>]*>Position\s*</th>#', $page);
        $this->assertStringNotContainsString('companySelect', $page);
        $this->assertDoesNotMatchRegularExpression('#<th [^>]*>Company\s*</th>|>Company\s*</label>#', $page);

        // Added as the form sends it, without a company: the new account is in the company of whoever added it.
        $form = ['name' => 'Budi Santoso', 'email' => 'budi@merdekabattery.com', 'position' => 'IT System Support', 'role_id' => [$this->roleId('Viewer Radio')]];
        $this->postJson(route('user-management.user-registered.store'), $form)->assertOk();
        $user = User::where('email', 'budi@merdekabattery.com')->sole();
        $this->assertSame(['IT System Support', (int) $admin->company_id], [$user->position, (int) $user->company_id]);

        // The list and the form of an edit show it; the filter finds it.
        $list = fn (array $query = []) => collect($this->getJson(route('user-management.user-registered.list', $query))->assertOk()->json('data'));
        $this->assertSame('IT System Support', $list()->firstWhere('email', 'budi@merdekabattery.com')['position']);
        $this->assertSame('-', $list()->firstWhere('email', $admin->email)['position'], 'an account from before has none yet');
        $this->assertSame(['budi@merdekabattery.com'], $list(['position' => ['IT System Support']])->pluck('email')->all());
        $this->assertSame([], $list(['position' => ['IT Field']])->all());
        $this->assertSame([], $list(['position' => ['Direktur']])->all(), 'what is no position finds nobody');
        $this->getJson(route('user-management.user-registered.show', $user->id))->assertOk()->assertJsonPath('data.position', 'IT System Support');

        // Changed: the position is what was chosen, the company stays what it was.
        DB::table('users')->where('id', $user->id)->update(['company_id' => 1]);
        $this->putJson(route('user-management.user-registered.update', $user->id), ['position' => 'IT Assistant'] + $form)->assertOk();
        $this->assertSame(['IT Assistant', 1], [$user->fresh()->position, (int) $user->fresh()->company_id]);

        // One of the six, and one is asked for.
        foreach ([[], ['position' => ''], ['position' => 'Direktur'], ['position' => ['IT Field']]] as $wrong) {
            $payload = $wrong + array_diff_key(['email' => 'lain@merdekabattery.com'] + $form, ['position' => 1]);
            $this->postJson(route('user-management.user-registered.store'), $payload)->assertStatus(422);
            $this->putJson(route('user-management.user-registered.update', $user->id), $wrong + array_diff_key($form, ['position' => 1]))->assertStatus(422);
        }
        $this->assertFalse(User::where('email', 'lain@merdekabattery.com')->exists());
        $this->assertSame('IT Assistant', $user->fresh()->position);

        // An account that was removed and is added again keeps the company it had.
        $user->delete();
        $this->postJson(route('user-management.user-registered.store'), ['position' => 'IT Field'] + $form)->assertOk();
        $this->assertSame(['IT Field', 1], [$user->fresh()->position, (int) $user->fresh()->company_id]);
    }

    public function test_before_the_migration_has_run_a_user_is_not_saved_and_the_reason_is_said(): void
    {
        // The code of this folder is live as soon as it is saved; the migration is run by hand, later.
        \Illuminate\Support\Facades\Schema::table('users', fn ($table) => $table->dropColumn('position'));
        \Illuminate\Support\Facades\Cache::forget('users-position-column');
        $this->actingAs($this->admin());

        $this->get(route('user-management.user-registered.index'))->assertOk();
        $this->getJson(route('user-management.user-registered.list'))->assertOk()->assertJsonPath('data.0.position', '-');
        $this->postJson(route('user-management.user-registered.store'), $this->form())->assertStatus(503)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'php artisan migrate'));
        $this->putJson(route('user-management.user-registered.update', $this->admin()->id), $this->form())->assertStatus(503);
        $this->assertFalse(User::where('email', 'budi@merdekabattery.com')->exists());
    }

    public function test_pages_and_lists_load(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('user-management.user-registered.index'))->assertOk();
        $this->getJson(route('user-management.user-registered.list'))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.role', 'Super Admin');
        // The role page used to fail on SQL Server ("SELECT * ... GROUP BY site_owner").
        $this->get(route('user-management.role-permission.index'))->assertOk();
        $this->getJson(route('user-management.role-permission.list'))->assertOk();
    }

    public function test_a_user_can_be_added_deleted_and_added_again(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('user-management.user-registered.store'), $this->form())->assertOk();
        $user = User::where('email', 'budi@merdekabattery.com')->firstOrFail();
        $this->assertSame(['Viewer Radio'], $user->roles->pluck('name')->all());
        $this->assertNotSame('', (string) $user->password); // random hash: e-mail + password login stays closed

        // The same e-mail twice is refused while the account exists.
        $this->postJson(route('user-management.user-registered.store'), $this->form())->assertStatus(422);

        // Deleted users disappear from the list straight away ...
        $this->deleteJson(route('user-management.user-registered.destroy', $user->id))->assertOk();
        $this->getJson(route('user-management.user-registered.list', ['search' => 'budi@']))->assertOk()->assertJsonPath('meta.total', 0);
        $this->deleteJson(route('user-management.user-registered.destroy', $user->id))->assertNotFound();

        // ... and the same person can be registered again (the old row comes back, no duplicate e-mail error).
        $this->postJson(route('user-management.user-registered.store'), $this->form([
            'name' => 'Budi S', 'role_id' => [$this->roleId('Admin Radio')],
        ]))->assertOk();
        $again = User::where('email', 'budi@merdekabattery.com')->firstOrFail();
        $this->assertSame($user->id, $again->id);
        $this->assertSame('Budi S', $again->name);
        $this->assertSame(['Admin Radio'], $again->roles->pluck('name')->all());
        $this->assertSame(1, User::withTrashed()->where('email', 'budi@merdekabattery.com')->count());
    }

    public function test_nobody_deletes_their_own_account_or_drops_their_own_super_admin_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->deleteJson(route('user-management.user-registered.destroy', $admin->id))->assertStatus(422);
        $this->assertNotNull(User::find($admin->id));

        $this->putJson(route('user-management.user-registered.update', $admin->id), $this->form([
            'name' => $admin->name, 'email' => $admin->email, 'role_id' => [$this->roleId('Viewer Radio')],
        ]))->assertStatus(422);
        $this->assertTrue($admin->fresh()->roles->contains('id', 1));
    }

    public function test_only_a_super_admin_hands_out_or_manages_super_admin(): void
    {
        $admin = $this->admin();
        $manager = $this->user('manager@merdekabattery.com', $this->role('Admin User', ['user-management.user-registered']));
        $this->actingAs($manager);

        // May manage ordinary users ...
        $this->postJson(route('user-management.user-registered.store'), $this->form())->assertOk();
        // ... but not create a Super Admin, promote themselves, or touch the Super Admin account.
        $this->postJson(route('user-management.user-registered.store'), $this->form([
            'email' => 'evil@merdekabattery.com', 'role_id' => [1],
        ]))->assertForbidden();
        $this->putJson(route('user-management.user-registered.update', $manager->id), $this->form([
            'name' => 'Tester', 'email' => $manager->email, 'role_id' => [1],
        ]))->assertForbidden();
        $this->getJson(route('user-management.user-registered.show', $admin->id))->assertForbidden();
        $this->deleteJson(route('user-management.user-registered.destroy', $admin->id))->assertForbidden();

        $this->assertFalse(User::where('email', 'evil@merdekabattery.com')->exists());
        $this->assertFalse($manager->fresh()->roles->contains('id', 1));
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_a_role_limited_to_one_company_only_manages_users_of_that_company(): void
    {
        $mti = (int) DB::table('companies')->where('company_orangehr_code', 'MTI')->value('id');
        $this->assertNotSame(3, $mti);

        // Company 3 is MCG; setting_role holds the company ids of a COMPANY-scoped role.
        $roleId = $this->role('Admin User MCG', ['user-management.user-registered']);
        DB::table('roles')->where('id', $roleId)->update(['func_role' => 'COMPANY', 'setting_role' => json_encode(['3'])]);
        $manager = $this->user('manager.mcg@merdekabattery.com', $roleId);
        $inside = $this->user('inside@merdekabattery.com', $this->roleId('Viewer Radio'));
        $outside = $this->user('outside@merdekabattery.com', $this->roleId('Viewer Radio'));
        DB::table('users')->where('id', $outside->id)->update(['company_id' => $mti]);
        $gone = $this->user('gone@merdekabattery.com', $this->roleId('Viewer Radio'));
        DB::table('users')->where('id', $gone->id)->update(['company_id' => $mti, 'deleted_at' => now()]);

        $this->actingAs($manager);

        // Reading: only their company.
        $listed = $this->getJson(route('user-management.user-registered.list'))->assertOk()->json('data.*.email');
        $this->assertEqualsCanonicalizing([$manager->email, $inside->email], $listed);
        $this->getJson(route('user-management.user-registered.show', $inside->id))->assertOk();
        $this->getJson(route('user-management.user-registered.show', $outside->id))->assertForbidden();

        // Writing: not on a user of another company ...
        $this->putJson(route('user-management.user-registered.update', $outside->id), $this->form([
            'name' => 'Diambil', 'email' => $outside->email, 'position' => 'IT Field', 'company_id' => 'MCG',
        ]))->assertForbidden();
        $this->deleteJson(route('user-management.user-registered.destroy', $outside->id))->assertForbidden();
        // ... not into another company, by creating or by moving ...
        $this->postJson(route('user-management.user-registered.store'), $this->form([
            'email' => 'baru@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MTI', 'role_id' => [$roleId],
        ]))->assertForbidden()->assertJsonPath('message', 'You are not allowed to manage users of this company.');
        $this->putJson(route('user-management.user-registered.update', $inside->id), $this->form([
            'name' => 'Tester', 'email' => $inside->email, 'position' => 'IT Field', 'company_id' => 'MTI',
        ]))->assertForbidden()->assertJsonPath('message', 'You are not allowed to manage users of this company.');
        // ... and not by re-adding a deleted user who belonged to another company.
        $this->postJson(route('user-management.user-registered.store'), $this->form([
            'email' => $gone->email, 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$roleId],
        ]))->assertForbidden()->assertJsonPath('message', 'You are not allowed to manage this user.');

        $this->assertFalse(User::where('email', 'baru@merdekabattery.com')->exists());
        $this->assertSame($mti, (int) $outside->fresh()->company_id);
        $this->assertSame('Tester', $outside->fresh()->name);
        $this->assertSame(3, (int) $inside->fresh()->company_id);
        $this->assertTrue(User::withTrashed()->find($gone->id)->trashed());

        // Inside their company everything works.
        $this->postJson(route('user-management.user-registered.store'), $this->form([
            'email' => 'baru@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$roleId],
        ]))->assertOk();
        $this->putJson(route('user-management.user-registered.update', $inside->id), $this->form([
            'name' => 'Inside Baru', 'email' => $inside->email, 'position' => 'IT Field', 'company_id' => 'MCG',
        ]))->assertOk();
        $this->assertSame('Inside Baru', $inside->fresh()->name);
    }

    public function test_a_company_limited_manager_cannot_hand_out_roles_that_are_not_limited(): void
    {
        $mti = (int) DB::table('companies')->where('company_orangehr_code', 'MTI')->value('id');
        $limit = fn (int $roleId, int $companyId) => DB::table('roles')->where('id', $roleId)
            ->update(['func_role' => 'COMPANY', 'setting_role' => json_encode([(string) $companyId])]);

        $own = $this->role('Admin User MCG', ['user-management.user-registered']);
        $limit($own, 3);
        $otherCompany = $this->role('Admin User MTI', ['user-management.user-registered']);
        $limit($otherCompany, $mti);
        // Covers their company and one more: handing it out would widen someone's reach.
        $regional = $this->role('Admin User Regional', ['user-management.user-registered']);
        DB::table('roles')->where('id', $regional)->update(['func_role' => 'COMPANY', 'setting_role' => json_encode(['3', (string) $mti])]);
        // Company 13 is not company 3 (the ids are compared whole, also when stored as numbers).
        $thirteen = $this->role('Admin User 13', ['user-management.user-registered']);
        DB::table('roles')->where('id', $thirteen)->update(['func_role' => 'COMPANY', 'setting_role' => json_encode([13])]);
        $adminRadio = $this->roleId('Admin Radio'); // type ALL
        $viewer = $this->roleId('Viewer Radio');    // type ALL

        $manager = $this->user('manager.mcg@merdekabattery.com', $own);
        $colleague = $this->user('kolega@merdekabattery.com', $viewer); // role given by a Super Admin
        $outside = $this->user('outside@merdekabattery.com', $viewer);
        DB::table('users')->where('id', $outside->id)->update(['company_id' => $mti]);
        $roleIds = fn (User $user) => $user->fresh()->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->actingAs($manager);
        $new = fn (array $roles) => $this->postJson(route('user-management.user-registered.store'), $this->form([
            'email' => 'baru@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => $roles,
        ]));

        // A role that is not limited, one limited to another company, or one that reaches
        // further than their own: not theirs to give.
        $new([$adminRadio])->assertForbidden();
        $new([$own, $adminRadio])->assertForbidden();
        $new([$otherCompany])->assertForbidden();
        $new([$regional])->assertForbidden();
        $new([$thirteen])->assertForbidden();
        $this->assertFalse(User::where('email', 'baru@merdekabattery.com')->exists());
        // Not to themselves either.
        foreach ([$adminRadio, $regional] as $more) {
            $this->putJson(route('user-management.user-registered.update', $manager->id), $this->form([
                'name' => 'Tester', 'email' => $manager->email, 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$own, $more],
            ]))->assertForbidden();
        }
        $this->assertSame([$own], $roleIds($manager));

        // Their own kind of role is fine, and what a user already has from someone else stays.
        $new([$own])->assertOk();
        $this->putJson(route('user-management.user-registered.update', $colleague->id), $this->form([
            'name' => 'Kolega', 'email' => $colleague->email, 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$own],
        ]))->assertOk();
        $this->assertEqualsCanonicalizing([$own, $viewer], $roleIds($colleague));

        // Holding an unlimited role as well does not lift the limit, whichever role comes first.
        foreach ([[$adminRadio, $own], [$own, $adminRadio]] as $order) {
            $manager->roles()->detach();
            foreach ($order as $roleId) {
                $manager->roles()->attach($roleId);
            }
            $this->actingAs($manager->fresh());

            $listed = $this->getJson(route('user-management.user-registered.list', ['paginated' => 100]))->assertOk()->json('data.*.email');
            $this->assertNotContains($outside->email, $listed);
            $this->assertContains($colleague->email, $listed);
            $this->getJson(route('user-management.user-registered.show', $outside->id))->assertForbidden();
            $this->postJson(route('user-management.user-registered.store'), $this->form([
                'email' => 'lain@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$adminRadio],
            ]))->assertForbidden();
        }
    }

    public function test_a_manager_holding_two_limited_roles_reaches_the_companies_of_both(): void
    {
        $mti = (int) DB::table('companies')->where('company_orangehr_code', 'MTI')->value('id');
        $limit = fn (int $roleId, array $companyIds) => DB::table('roles')->where('id', $roleId)
            ->update(['func_role' => 'COMPANY', 'setting_role' => json_encode(array_map('strval', $companyIds))]);
        $mcg = $this->role('Admin User MCG', ['user-management.user-registered']);
        $limit($mcg, [3]);
        $mtiRole = $this->role('Admin User MTI', ['user-management.user-registered']);
        $limit($mtiRole, [$mti]);
        $regional = $this->role('Admin User Regional', ['user-management.user-registered']);
        $limit($regional, [3, $mti]);

        $inside = $this->user('inside@merdekabattery.com', $this->roleId('Viewer Radio'));
        $outside = $this->user('outside@merdekabattery.com', $this->roleId('Viewer Radio'));
        DB::table('users')->where('id', $outside->id)->update(['company_id' => $mti]);
        $manager = $this->user('manager@merdekabattery.com', $mcg);
        $manager->roles()->attach($mtiRole);
        $this->actingAs($manager->fresh());

        // Both companies, whichever role the database returns first.
        $listed = $this->getJson(route('user-management.user-registered.list', ['paginated' => 100]))->assertOk()->json('data.*.email');
        $this->assertContains($inside->email, $listed);
        $this->assertContains($outside->email, $listed);
        $this->getJson(route('user-management.user-registered.show', $outside->id))->assertOk();

        // A role covering exactly those two companies is now inside their reach.
        $this->postJson(route('user-management.user-registered.store'), $this->form([
            'email' => 'regional@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MTI', 'role_id' => [$regional],
        ]))->assertOk();
        $this->assertSame(['Admin User Regional'], User::where('email', 'regional@merdekabattery.com')->firstOrFail()->roles->pluck('name')->all());
    }

    public function test_the_reach_rule_covers_every_kind_of_limited_role(): void
    {
        $limited = function (string $name, string $kind, ?string $setting): int {
            $roleId = $this->role($name, ['user-management.user-registered']);
            DB::table('roles')->where('id', $roleId)->update(['func_role' => $kind, 'setting_role' => $setting]);

            return $roleId;
        };
        $attempt = 0;
        $give = function (int $roleId) use (&$attempt) {
            return $this->postJson(route('user-management.user-registered.store'), $this->form([
                'email' => 'baru' . ++$attempt . '@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$roleId],
            ]));
        };

        // HO: the same site owner, as the role form stores it (a plain string). Company MCG sits on a site of owner MCG.
        $ho = $limited('HO MCG', 'HO', 'MCG');
        $hoSame = $limited('HO MCG kedua', 'HO', 'MCG');
        $hoOther = $limited('HO MBM', 'HO', 'MBM');
        $this->actingAs($this->user('ho@merdekabattery.com', $ho));
        $give($hoSame)->assertOk();
        $give($hoOther)->assertForbidden();

        // SITE: every site of the role must be one of the caller's (company MCG is on site 1).
        $site = $limited('Site 1+4', 'SITE', json_encode(['1', '4']));
        $siteInside = $limited('Site 1', 'SITE', json_encode(['1']));
        $siteWider = $limited('Site 1+4+5', 'SITE', json_encode(['1', '4', '5']));
        $this->actingAs($this->user('site@merdekabattery.com', $site));
        $give($siteInside)->assertOk();
        $give($siteWider)->assertForbidden();
        $give($hoSame)->assertForbidden(); // another kind of role, whatever it reaches

        // A role saved without any company (the form stores "null") reaches nothing and is nobody's to give.
        $company = $limited('Company 3', 'COMPANY', json_encode(['3']));
        $empty = $limited('Company kosong', 'COMPANY', json_encode(null));
        $this->actingAs($this->user('company@merdekabattery.com', $company));
        $give($company)->assertOk();
        $give($empty)->assertForbidden();
    }

    public function test_a_deleted_role_cannot_be_handed_out(): void
    {
        $gone = $this->role('Role Lama', ['radio-management.history']);
        DB::table('roles')->where('id', $gone)->update(['deleted_at' => now()]);
        $staff = $this->user('staf@merdekabattery.com', $this->roleId('Viewer Radio'));
        $this->actingAs($this->admin());

        $this->postJson(route('user-management.user-registered.store'), $this->form(['role_id' => [$gone]]))->assertStatus(422);
        $this->putJson(route('user-management.user-registered.update', $staff->id), $this->form([
            'name' => 'Staf', 'email' => $staff->email, 'role_id' => [$gone],
        ]))->assertStatus(422);

        $this->assertFalse(User::where('email', 'budi@merdekabattery.com')->exists());
        $this->assertSame(['Viewer Radio'], $staff->fresh()->roles->pluck('name')->all());
    }

    public function test_a_caller_who_is_not_limited_replaces_the_roles_of_a_user(): void
    {
        $viewer = $this->roleId('Viewer Radio');
        $adminRadio = $this->roleId('Admin Radio');
        $manager = $this->user('manager@merdekabattery.com', $this->role('Admin User', ['user-management.user-registered'])); // type ALL
        $staff = $this->user('staf@merdekabattery.com', $viewer);
        $roleNames = fn () => $staff->fresh()->roles->pluck('name')->all();
        $edit = fn (array $roles) => $this->putJson(route('user-management.user-registered.update', $staff->id), $this->form([
            'name' => 'Staf', 'email' => $staff->email, 'role_id' => $roles,
        ]));

        // The ticked roles are the roles the user ends up with: for a Super Admin ...
        $this->actingAs($this->admin());
        $edit([$adminRadio])->assertOk();
        $this->assertSame(['Admin Radio'], $roleNames());
        $this->getJson(route('user-management.user-registered.show', $staff->id))->assertOk()->assertJsonPath('data.other_role_count', 0);

        // ... and for a user manager whose role is not limited to a company.
        $this->actingAs($manager);
        $edit([$viewer])->assertOk();
        $this->assertSame(['Viewer Radio'], $roleNames());
        $edit([$viewer, $adminRadio])->assertOk();
        $this->assertEqualsCanonicalizing(['Admin Radio', 'Viewer Radio'], $roleNames());
        $edit([])->assertStatus(422); // every role is on their form, so one must be ticked
    }

    public function test_a_company_limited_manager_cannot_make_anyone_less_limited(): void
    {
        $mti = (int) DB::table('companies')->where('company_orangehr_code', 'MTI')->value('id');
        $own = $this->role('Admin User MCG', ['user-management.user-registered']);
        DB::table('roles')->where('id', $own)->update(['func_role' => 'COMPANY', 'setting_role' => json_encode(['3'])]);
        $unlimited = $this->role('Admin User', ['user-management.user-registered']); // type ALL, same page
        $viewer = $this->roleId('Viewer Radio');
        $roleIds = fn (User $user) => $user->fresh()->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $edit = fn (User $user, array $roles) => $this->putJson(route('user-management.user-registered.update', $user->id), [
            'name' => 'Tester', 'email' => $user->email, 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => $roles,
        ]);

        $outside = $this->user('outside@merdekabattery.com', $viewer);
        DB::table('users')->where('id', $outside->id)->update(['company_id' => $mti]);

        // Limited and unlimited role on the same account: the limit holds, and it cannot be dropped ...
        $manager = $this->user('manager.mcg@merdekabattery.com', $own);
        $manager->roles()->attach($unlimited);
        $this->actingAs($manager = $manager->fresh());
        $response = $edit($manager, [$unlimited])->assertStatus(422);
        $this->assertStringContainsString('cannot remove your own role', implode(' ', (array) $response->json('errors')));
        $this->assertEqualsCanonicalizing([$own, $unlimited], $roleIds($manager));
        $this->getJson(route('user-management.user-registered.show', $outside->id))->assertForbidden();

        // ... nor taken off a colleague who would then manage every company.
        $colleague = $this->user('kolega@merdekabattery.com', $own);
        $colleague->roles()->attach($unlimited);
        $response = $edit($colleague, [$unlimited])->assertStatus(422);
        $this->assertStringContainsString('lift the company limit', implode(' ', (array) $response->json('errors')));
        $this->assertEqualsCanonicalizing([$own, $unlimited], $roleIds($colleague));

        // Taking user management away altogether is fine: nothing unlimited is left behind.
        $assistant = $this->user('asisten@merdekabattery.com', $own);
        $assistant->roles()->attach($viewer);
        $edit($assistant, [])->assertOk();
        $this->assertSame([$viewer], $roleIds($assistant));

        // A colleague who never was limited is not made so by the rule: renaming them works and
        // their role, which is not the manager's to manage, stays.
        $free = $this->user('bebas@merdekabattery.com', $unlimited);
        $this->putJson(route('user-management.user-registered.update', $free->id), [
            'name' => 'Admin Bebas', 'email' => $free->email, 'position' => 'IT Field', 'company_id' => 'MCG',
        ])->assertOk();
        $this->assertSame('Admin Bebas', $free->fresh()->name);
        $this->assertSame([$unlimited], $roleIds($free));
    }

    public function test_a_limited_manager_can_edit_a_user_whose_roles_they_cannot_manage(): void
    {
        $own = $this->role('Admin User MCG', ['user-management.user-registered']);
        DB::table('roles')->where('id', $own)->update(['func_role' => 'COMPANY', 'setting_role' => json_encode(['3'])]);
        $viewer = $this->roleId('Viewer Radio');
        $manager = $this->user('manager.mcg@merdekabattery.com', $own);
        $staff = $this->user('staf@merdekabattery.com', $viewer); // the only role is one the manager's form does not list

        $this->actingAs($manager);

        // The form is told so (and then accepts a save without any box ticked) ...
        $this->getJson(route('user-management.user-registered.show', $staff->id))->assertOk()
            ->assertJsonPath('data.other_role_count', 1);
        $this->getJson(route('user-management.user-registered.show', $manager->id))->assertOk()
            ->assertJsonPath('data.other_role_count', 0);

        // ... and a rename does not force the manager to hand out their own role.
        $this->putJson(route('user-management.user-registered.update', $staff->id), [
            'name' => 'Staf Baru', 'email' => $staff->email, 'position' => 'IT Field', 'company_id' => 'MCG',
        ])->assertOk();
        $staff->refresh();
        $this->assertSame('Staf Baru', $staff->name);
        $this->assertSame(['Viewer Radio'], $staff->roles->pluck('name')->all());

        // Sign-in goes by e-mail: pointing that account at another address would hand its roles
        // to whoever owns the address, so the e-mail of such a user is not theirs to change.
        $response = $this->putJson(route('user-management.user-registered.update', $staff->id), [
            'name' => 'Staf Baru', 'email' => 'milik.manager@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG',
        ])->assertForbidden();
        $this->assertStringContainsString('cannot change the e-mail', implode(' ', (array) $response->json('errors')));
        $this->assertSame('staf@merdekabattery.com', $staff->fresh()->email);
        // (the same address in another letter case is not a change, and a user whose roles are
        // all theirs to manage can be given a new address)
        $this->putJson(route('user-management.user-registered.update', $staff->id), [
            'name' => 'Staf Baru', 'email' => 'STAF@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG',
        ])->assertOk();
        $managed = $this->user('dikelola@merdekabattery.com', $own);
        $this->putJson(route('user-management.user-registered.update', $managed->id), [
            'name' => 'Dikelola', 'email' => 'alamat.baru@merdekabattery.com', 'position' => 'IT Field', 'company_id' => 'MCG', 'role_id' => [$own],
        ])->assertOk();
        $this->assertSame('alamat.baru@merdekabattery.com', $managed->fresh()->email);

        // A user who would be left with no role at all is still refused, as for everyone else.
        $bare = $this->user('kosong@merdekabattery.com', $own);
        $this->putJson(route('user-management.user-registered.update', $bare->id), [
            'name' => 'Kosong', 'email' => $bare->email, 'position' => 'IT Field', 'company_id' => 'MCG',
        ])->assertStatus(422);
        $this->actingAs($this->admin());
        $this->putJson(route('user-management.user-registered.update', $staff->id), [
            'name' => 'Staf', 'email' => $staff->email, 'position' => 'IT Field', 'company_id' => 'MCG',
        ])->assertStatus(422); // an unlimited caller sees every role, so one must be ticked
        $this->getJson(route('user-management.user-registered.show', $staff->id))->assertJsonPath('data.other_role_count', 0);
    }

    public function test_after_sign_in_a_user_lands_on_a_page_their_role_may_open(): void
    {
        // Super Admin and the radio roles start on the dashboard.
        $this->assertSame('dashboard.index', $this->admin()->homeRoute());
        $this->assertSame('dashboard.index', $this->user('viewer@merdekabattery.com', $this->roleId('Viewer Radio'))->homeRoute());

        // A role without the dashboard starts on its first menu instead of a 403 page.
        $historyOnly = $this->user('history@merdekabattery.com', $this->role('Riwayat Saja', ['radio-management.history']));
        $this->assertSame('radio-management.history.index', $historyOnly->homeRoute());
        $this->actingAs($historyOnly)->get('/')->assertRedirect(route('radio-management.history.index'));
        $this->get(route('radio-management.history.index'))->assertOk();
        $this->get(route('dashboard.index'))->assertForbidden();
    }
}
