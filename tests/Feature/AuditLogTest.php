<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\AuditLogMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use RuntimeException;
use Tests\Concerns\FakesDirectory;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * "Audit Log": the trail of sign-ins and changes (after the page of the same name in trilingual-docon). Written after
 * an action has been done, with the values before and after, never a secret; read on a page of its own that changes
 * nothing.
 */
class AuditLogTest extends TestCase
{
    use FakesDirectory;
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    /** @return Collection<int, AuditLog> the entries of one action, oldest first */
    private function entries(string $action): Collection
    {
        return AuditLog::query()->where('action', $action)->orderBy('id')->get();
    }

    private function only(string $action): AuditLog
    {
        $found = $this->entries($action);
        $this->assertCount(1, $found, "one entry {$action}");

        return $found->first();
    }

    /** Every entry as stored, as one text: what must never be in it, must not be in this. */
    private function everything(): string
    {
        return (string) json_encode(DB::table('audit_logs')->get());
    }

    /** A signature as the pad sends it: a few dark strokes on nothing, as a data: address. */
    private static function drawn(): string
    {
        $picture = imagecreatetruecolor(600, 240);
        imagealphablending($picture, false);
        imagesavealpha($picture, true);
        imagefill($picture, 0, 0, imagecolorallocatealpha($picture, 0, 0, 0, 127));
        imagesetthickness($picture, 4);
        $black = imagecolorallocatealpha($picture, 16, 16, 16, 0);
        imageline($picture, 20, 200, 300, 30, $black);
        imageline($picture, 300, 30, 580, 210, $black);
        ob_start();
        imagepng($picture);
        $png = (string) ob_get_clean();
        imagedestroy($picture);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** A database whose sidebar has no "Audit Log" yet (its grants go first: they point at it). */
    private function withoutTheMenu(): void
    {
        $menuIds = DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->pluck('id');
        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();
        Cache::forget('rbac_menu_route_key_map');
    }

    private function radioPayload(array $overrides = []): array
    {
        return array_merge([
            'dmr_id' => 2001,
            'serial_number' => '867tzb9999',
            'radio_type_id' => RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id'),
            'user_name' => 'Budi Santoso',
            'placement' => 'di_user',
            'condition' => 'layak',
        ], $overrides);
    }

    // ================================================================ the menu and the page

    public function test_the_menu_is_in_the_section_settings_and_for_super_admin_alone(): void
    {
        $menu = DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->sole();
        $this->assertSame(['Audit Log', null, 'Settings', 'shield-search'], [$menu->title, $menu->parent_id, $menu->section, $menu->icon]);
        // It took the place of "Approval Management" (hidden), which moved down by one: "Settings" is printed above it.
        $approvals = DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->sole();
        $this->assertSame([11, 12], [(int) $menu->order, (int) $approvals->order]);
        $this->assertSame([1], DB::table('menu_role')->where('menu_id', $menu->id)->pluck('role_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(array_values(CheckMenuPermission::PERMISSION_IDS),
            DB::table('menu_role_permission')->where('menu_id', $menu->id)->where('role_id', 1)->orderBy('permission_id')->pluck('permission_id')->map(fn ($id) => (int) $id)->all());

        // Seeding again adds nothing and moves nothing.
        (new AuditLogMenuSeeder())->run();
        $this->assertSame(1, DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->count());
        $this->assertSame(12, (int) DB::table('menu')->where('id', $approvals->id)->value('order'));

        // Super Admin: in the sidebar under "Settings", and the page of trilingual-docon.
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('audit-log.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#subheader[^>]*>\s*Settings\s*</span>.*?href="' . preg_quote(route('audit-log.index'), '#') . '".*?Audit Log#s', $page);
        foreach (['Audit log', 'data-audit-view="table"', 'data-audit-view="timeline"', 'Log Activity', 'User email', 'Search by email', 'All actions'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        preg_match_all('#<th scope="col" class="fs-5">([^<]*)</th>#', $page, $titles);
        $this->assertSame(['When', 'User', 'Action', 'Entity', 'Change', 'IP'], $titles[1]);
        $this->assertStringNotContainsString('auditNotReady', $page);
        $this->getJson(route('audit-log.list'))->assertOk();

        // Another role: not in its sidebar, and closed.
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $this->assertStringNotContainsString(route('audit-log.index'), (string) $this->get(route('dashboard.index'))->getContent());
        $this->get(route('audit-log.index'))->assertForbidden();
        $this->getJson(route('audit-log.list'))->assertForbidden();
    }

    public function test_without_its_menu_the_page_is_closed_to_everyone_but_super_admin(): void
    {
        $this->withoutTheMenu();

        // The `permission` middleware alone would let any account in to a route without a menu.
        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->get(route('audit-log.index'))->assertForbidden();
        $this->getJson(route('audit-log.list'))->assertForbidden();

        $this->actingAs($this->superAdmin());
        $this->get(route('audit-log.index'))->assertOk();
    }

    public function test_the_migration_adds_the_entry_to_a_database_in_use_and_takes_it_away_again(): void
    {
        $this->withoutTheMenu();
        DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->update(['order' => 11]);
        $migration = require database_path('migrations/2026_10_17_110000_add_audit_log_menu.php');

        $migration->up();
        $this->assertSame(11, (int) DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->value('order'));
        $this->assertSame(12, (int) DB::table('menu')->where('title', 'Approval Management')->value('order'));

        $migration->down();
        $this->assertFalse(DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->exists());
        $this->assertSame(11, (int) DB::table('menu')->where('title', 'Approval Management')->value('order'));

        // A sidebar that was rearranged: the entry goes at the end, nothing else moves.
        DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->update(['order' => 4]);
        $end = (int) DB::table('menu')->whereNull('parent_id')->max('order') + 1;
        $migration->up();
        $this->assertSame($end, (int) DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->value('order'));
        $this->assertSame(4, (int) DB::table('menu')->where('title', 'Approval Management')->value('order'));
    }

    public function test_the_page_lists_the_entries_newest_first_and_filters_them(): void
    {
        $admin = $this->superAdmin();
        $budi = $this->makeUser('budi@test.local', 'Admin Radio', 'Budi Santoso');
        $logger = app(AuditLogger::class);

        $this->actingAs($budi);
        $first = $logger->log(AuditLogger::USER_STATUS_CHANGED, $admin, ['Status' => 'Active'], ['Status' => 'Inactive'], label: $admin->email);
        $this->actingAs($admin);
        $second = $logger->log(AuditLogger::RADIO_CREATED, null, null, ['Radio ID' => '2001'], label: 'ID 2001 · 867TZB9999');
        // A failed sign-in: nobody signed in, what was typed is in its values.
        auth()->logout();
        $third = $logger->log(AuditLogger::LOGIN_FAILED, new: ['Account' => 'siti.rahma', 'Via' => 'LDAP', 'Reason' => 'Wrong password']);
        // Written in the same second: the order still follows the order of writing.
        DB::table('audit_logs')->update(['created_at' => now()->startOfSecond()]);

        $this->actingAs($admin);
        $list = fn (array $query = []) => $this->getJson(route('audit-log.list', $query))->assertOk();

        $all = $list()->json();
        $this->assertSame([$third->id, $second->id, $first->id], array_column($all['data'], 'id'));
        $this->assertSame(3, $all['meta']['total']);

        // A row as the table draws it.
        $row = $all['data'][2];
        $this->assertSame(['Budi Santoso', 'budi@test.local', 'user.status_changed', 'changed the status of', 'User #' . $admin->id, $admin->email, '127.0.0.1'],
            [$row['user'], $row['email'], $row['action'], $row['activity'], $row['entity'], $row['label'], $row['ip']]);
        $this->assertSame([['field' => 'Status', 'old' => 'Active', 'new' => 'Inactive']], $row['changes']);
        $this->assertMatchesRegularExpression('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $row['at']);
        // Nobody signed in: a guest. A value that is new has no old one.
        $this->assertSame(['Guest', null], [$all['data'][0]['user'], $all['data'][0]['email']]);
        $this->assertSame(['field' => 'Radio ID', 'old' => null, 'new' => '2001'], $all['data'][1]['changes'][0]);

        // By the address, by the name of the account, by what was typed into a failed sign-in.
        $this->assertSame([$first->id], array_column($list(['search' => 'budi@test'])->json('data'), 'id'));
        $this->assertSame([$first->id], array_column($list(['search' => 'Santoso'])->json('data'), 'id'));
        $this->assertSame([$third->id], array_column($list(['search' => 'siti.rahma'])->json('data'), 'id'));
        // Taken literally: "_" is no wildcard.
        $this->assertSame([], $list(['search' => 'budi_test'])->json('data'));

        // By the action; and a page at a time.
        $this->assertSame([$second->id], array_column($list(['action' => 'radio.created'])->json('data'), 'id'));
        $paged = $list(['paginated' => 2, 'page' => 2])->json();
        $this->assertSame([$first->id], array_column($paged['data'], 'id'));
        $this->assertSame([2, 2, 3], [$paged['meta']['current_page'], $paged['meta']['last_page'], $paged['meta']['total']]);

        // The page offers the actions there are, and opens on what its address says.
        $page = (string) $this->get(route('audit-log.index', ['view' => 'timeline', 'action' => 'auth.login_failed', 'q' => 'siti']))->assertOk()->getContent();
        foreach (['auth.login_failed', 'radio.created', 'user.status_changed'] as $action) {
            $this->assertStringContainsString('<option value="' . $action . '"', $page);
        }
        $this->assertStringContainsString('<option value="auth.login_failed" selected', $page);
        $this->assertStringContainsString('value="siti"', $page);
        $this->assertMatchesRegularExpression('#id="auditTableView" class="d-none"#', $page);
        $this->assertStringContainsString('view: "timeline"', $page);
        // Anything else in ?view= is the table; a list where one word is expected is no word.
        $this->assertStringContainsString('view: "table"', (string) $this->get(route('audit-log.index', ['view' => '../x']))->getContent());
        $this->get(route('audit-log.index', ['q' => ['a'], 'action' => ['b'], 'view' => ['timeline']]))->assertOk();
        $this->assertSame(3, $list(['search' => ['budi'], 'action' => ['x'], 'paginated' => ['2']])->json('meta.total'));
    }

    // ================================================================ signing in and out

    public function test_signing_in_and_out_is_recorded_and_no_password_is(): void
    {
        $staff = $this->makeUser('staf@test.local', 'Viewer Radio', 'Staf Gudang');

        $this->post('/login', ['emailusername' => 'staf@test.local', 'password' => 'Salah-Sandi-123']);
        $this->post('/login', ['emailusername' => 'orang.lain@test.local', 'password' => 'Sandi-Lain-456']);
        $this->post('/login', ['emailusername' => 'staf@test.local', 'password' => 'secret-password'])->assertRedirect(route('dashboard.index'));
        $this->post(route('logout'))->assertRedirect(route('login'));

        [$wrong, $unknown] = $this->entries(AuditLogger::LOGIN_FAILED)->all();
        $this->assertSame(['Account' => 'staf@test.local', 'Via' => 'Password', 'Reason' => 'Wrong password'], $wrong->new_values);
        $this->assertSame(['Account' => 'orang.lain@test.local', 'Via' => 'Login form', 'Reason' => 'Not registered'], $unknown->new_values);
        // Nobody was signed in: the entries name no actor, and the page calls them guests.
        $this->assertSame([null, null, 'Guest'], [$wrong->user_id, $wrong->user_email, $wrong->actorName()]);

        $in = $this->only(AuditLogger::LOGIN);
        $this->assertSame([(int) $staff->id, 'staf@test.local', ['Via' => 'Password'], '127.0.0.1'], [(int) $in->user_id, $in->user_email, $in->new_values, $in->ip_address]);
        $this->assertNotSame('', (string) $in->user_agent);
        $out = $this->only(AuditLogger::LOGOUT);
        $this->assertSame((int) $staff->id, (int) $out->user_id);
        $this->assertSame('Staf Gudang signed out', $out->actorName() . ' ' . $out->activityLabel());

        foreach (['Salah-Sandi-123', 'Sandi-Lain-456', 'secret-password'] as $password) {
            $this->assertStringNotContainsString($password, $this->everything());
        }

        // An account set to inactive is refused like a wrong password on the screen; the trail says why.
        $staff->update(['active' => '0']);
        $this->post('/login', ['emailusername' => 'staf@test.local', 'password' => 'secret-password']);
        $this->assertSame('Account not active', $this->entries(AuditLogger::LOGIN_FAILED)->last()->new_values['Reason']);
    }

    public function test_a_refused_directory_sign_in_says_why(): void
    {
        $this->fakeDirectory(['siti.rahma' => 'Sandi-AD-789']);
        $siti = $this->makeUser('siti@test.local', 'Viewer Radio', 'Siti Rahma');
        $siti->forceFill(['ldap_username' => 'siti.rahma'])->save();

        $this->post('/login', ['emailusername' => 'siti.rahma', 'password' => 'Bukan-Sandinya-1']);
        $refused = $this->only(AuditLogger::LOGIN_FAILED);
        // Typed as the directory username: the address of the account is added.
        $this->assertSame(['Account' => 'siti.rahma', 'Via' => 'LDAP', 'Reason' => 'Wrong password', 'Email' => 'siti@test.local'], $refused->new_values);

        $this->post('/login', ['emailusername' => 'siti.rahma', 'password' => 'Sandi-AD-789'])->assertRedirect(route('dashboard.index'));
        $this->assertSame(['Via' => 'LDAP'], $this->only(AuditLogger::LOGIN)->new_values);
        $this->assertStringNotContainsString('Bukan-Sandinya-1', $this->everything());
        $this->assertStringNotContainsString('Sandi-AD-789', $this->everything());
    }

    // ================================================================ changes

    public function test_changes_to_accounts_are_recorded_with_before_and_after(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $viewer = (int) DB::table('roles')->where('name', 'Viewer Radio')->value('id');
        $radioAdmin = (int) DB::table('roles')->where('name', 'Admin Radio')->value('id');
        $form = ['name' => 'Budi Santoso', 'email' => 'budi@merdekabattery.com', 'role_id' => [$viewer], 'active' => '1'];

        $this->postJson(route('user-management.user-registered.store'), $form)->assertOk();
        $budi = User::where('email', 'budi@merdekabattery.com')->sole();
        $made = $this->only(AuditLogger::USER_CREATED);
        $this->assertSame([(int) $admin->id, 'User', (int) $budi->id, 'budi@merdekabattery.com'],
            [(int) $made->user_id, $made->entity_type, (int) $made->entity_id, $made->entity_label]);
        $this->assertSame(['Budi Santoso', 'budi@merdekabattery.com', 'Viewer Radio', 'Active'],
            [$made->new_values['Name'], $made->new_values['Email'], $made->new_values['Role'], $made->new_values['Status']]);
        $this->assertNull($made->old_values);

        // Only what moved.
        $this->putJson(route('user-management.user-registered.update', $budi->id), ['name' => 'Budi S', 'role_id' => [$radioAdmin]] + $form)->assertOk();
        $changed = $this->only(AuditLogger::USER_UPDATED);
        $this->assertSame(['Name' => 'Budi Santoso', 'Role' => 'Viewer Radio'], $changed->old_values);
        $this->assertSame(['Name' => 'Budi S', 'Role' => 'Admin Radio'], $changed->new_values);
        $this->assertSame(['Name: Budi Santoso → Budi S', 'Role: Viewer Radio → Admin Radio'], $changed->changeSummary());
        // Saved as it was: nothing to say.
        $this->putJson(route('user-management.user-registered.update', $budi->id), ['name' => 'Budi S', 'role_id' => [$radioAdmin]] + $form)->assertOk();
        $this->assertCount(1, $this->entries(AuditLogger::USER_UPDATED));

        $this->putJson(route('user-management.user-registered.status', $budi->id), ['active' => false])->assertOk();
        $status = $this->only(AuditLogger::USER_STATUS_CHANGED);
        $this->assertSame([['Status' => 'Active'], ['Status' => 'Inactive']], [$status->old_values, $status->new_values]);

        $this->putJson(route('user-management.user-registered.password', $budi->id), ['password' => 'Radio-2026x', 'password_confirmation' => 'Radio-2026x'])->assertOk();
        $password = $this->only(AuditLogger::USER_PASSWORD_RESET);
        $this->assertSame([null, null, (int) $budi->id], [$password->old_values, $password->new_values, (int) $password->entity_id]);
        $this->assertSame('set the password for', $password->activityLabel());

        $this->deleteJson(route('user-management.user-registered.destroy', $budi->id))->assertOk();
        $deleted = $this->only(AuditLogger::USER_DELETED);
        $this->assertSame(['Budi S', 'budi@merdekabattery.com', 'Admin Radio', 'Inactive'],
            [$deleted->old_values['Name'], $deleted->old_values['Email'], $deleted->old_values['Role'], $deleted->old_values['Status']]);

        // Added again: the deleted account comes back, and the trail says so.
        $this->postJson(route('user-management.user-registered.store'), $form)->assertOk();
        $this->assertSame('Yes, it had been deleted', $this->entries(AuditLogger::USER_CREATED)->last()->new_values['Restored']);

        $this->assertStringNotContainsString('Radio-2026x', $this->everything());
        $this->assertStringNotContainsString('$2y$', $this->everything());
    }

    public function test_changes_to_roles_say_which_access_moved(): void
    {
        $this->actingAs($this->superAdmin());
        $menu = (int) DB::table('menu')->where('route', 'dashboard.index')->value('id');
        $form = ['role_name' => 'Staf Gudang', 'home_page' => (string) $menu, 'menu_list' => [$menu], 'menu_permission' => [$menu => [2]]];

        $this->postJson(route('user-management.role-permission.store'), $form)->assertOk();
        $made = $this->only(AuditLogger::ROLE_CREATED);
        // The home page by the name of its menu, not by its number.
        $this->assertSame(['Name' => 'Staf Gudang', 'Home page' => 'Dashboard', 'Menu Dashboard' => 'Read'], $made->new_values);
        $this->assertSame('Staf Gudang', $made->entity_label);
        $role = (int) $made->entity_id;

        $this->putJson(route('user-management.role-permission.update', $role), ['menu_permission' => [$menu => [2, 3]]] + $form)->assertOk();
        $changed = $this->only(AuditLogger::ROLE_UPDATED);
        $this->assertSame([['Menu Dashboard' => 'Read'], ['Menu Dashboard' => 'Read, Update']], [$changed->old_values, $changed->new_values]);

        $this->deleteJson(route('user-management.role-permission.destroy', $role))->assertOk();
        $this->assertSame('Staf Gudang', $this->only(AuditLogger::ROLE_DELETED)->old_values['Name']);
    }

    public function test_every_change_to_a_radio_is_recorded(): void
    {
        $this->actingAs($this->superAdmin());

        $this->postJson(route('radio-management.radio.store'), $this->radioPayload())->assertOk();
        $radio = Radio::sole();
        $made = $this->only(AuditLogger::RADIO_CREATED);
        $this->assertSame(['Radio', (int) $radio->id, 'ID 2001 · 867TZB9999'], [$made->entity_type, (int) $made->entity_id, $made->entity_label]);
        $this->assertSame(['2001', '867TZB9999', 'Budi Santoso'], [$made->new_values['Radio ID'], $made->new_values['SN'], $made->new_values['User/Unit']]);

        $this->putJson(route('radio-management.radio.update', $radio), $this->radioPayload(['user_name' => 'Andi Wijaya']))->assertOk();
        $changed = $this->only(AuditLogger::RADIO_UPDATED);
        $this->assertSame([['User/Unit' => 'Budi Santoso'], ['User/Unit' => 'Andi Wijaya']], [$changed->old_values, $changed->new_values]);

        $this->putJson(route('radio-management.radio.archive', $radio), ['archive_reason' => 'Pindah Site', 'archived_at' => now()->toDateString(), 'note' => 'Dikirim ke Konawe'])->assertOk();
        $archived = $this->only(AuditLogger::RADIO_ARCHIVED);
        $this->assertSame(['Radio ID' => '2001'], $archived->old_values);
        $this->assertSame(['Radio ID' => null, 'Alasan' => 'Pindah Site', 'Tanggal' => now()->format('d-m-Y'), 'Catatan' => 'Dikirim ke Konawe'], $archived->new_values);

        $this->putJson(route('radio-management.radio.restore', $radio))->assertOk();
        $this->assertSame('2001', $this->only(AuditLogger::RADIO_RESTORED)->new_values['Radio ID']);

        $this->putJson(route('radio-management.radio.add-history', $radio), [
            'action' => 'Program', 'event_date' => now()->toDateString(), 'description' => 'Channel baru', 'document_no' => 'WO-12',
        ])->assertOk();
        $this->assertSame(['Jenis' => 'Program', 'Tanggal' => now()->format('d-m-Y'), 'Keterangan' => 'Channel baru', 'No. Dokumen' => 'WO-12'],
            $this->only(AuditLogger::RADIO_HISTORY_ADDED)->new_values);

        $this->deleteJson(route('radio-management.radio.destroy', $radio))->assertOk();
        $deleted = $this->only(AuditLogger::RADIO_DELETED);
        $this->assertSame(['2001', 'Andi Wijaya'], [$deleted->old_values['Radio ID'], $deleted->old_values['User/Unit']]);
        $this->assertSame('ID 2001 · 867TZB9999', $deleted->entity_label);
    }

    public function test_changes_to_master_data_are_recorded(): void
    {
        $this->actingAs($this->superAdmin());

        $this->postJson(route('radio-management.radio-type.store'), ['name' => 'Hytera PD-1', 'category' => 'HT', 'power' => '4 W', 'is_active' => true])->assertOk();
        $made = $this->only(AuditLogger::MASTER_CREATED);
        $this->assertSame(['RadioType', 'Tipe Radio: Hytera PD-1'], [$made->entity_type, $made->entity_label]);
        $this->assertSame(['Nama Tipe' => 'Hytera PD-1', 'Kategori' => 'HT (Portable)', 'Daya' => '4 W', 'Aktif' => 'Ya'], $made->new_values);

        $this->putJson(route('radio-management.radio-type.update', $made->entity_id), ['name' => 'Hytera PD-1', 'category' => 'HT', 'power' => '5 W', 'is_active' => false])->assertOk();
        $changed = $this->only(AuditLogger::MASTER_UPDATED);
        $this->assertSame([['Daya' => '4 W', 'Aktif' => 'Ya'], ['Daya' => '5 W', 'Aktif' => 'Tidak']], [$changed->old_values, $changed->new_values]);

        $this->deleteJson(route('radio-management.radio-type.destroy', $made->entity_id))->assertOk();
        $this->assertSame('Hytera PD-1', $this->only(AuditLogger::MASTER_DELETED)->old_values['Nama Tipe']);

        // Refused (still used by a radio): nothing is recorded.
        $department = RadioDepartment::firstOrCreate(['name' => 'MAINTENANCE']);
        Radio::create(['dmr_id' => 11, 'serial_number' => 'A1', 'user_name' => 'x', 'department_id' => $department->id]);
        $this->deleteJson(route('radio-management.department.destroy', $department->id))->assertStatus(422);
        $this->assertCount(1, $this->entries(AuditLogger::MASTER_DELETED));
    }

    public function test_signatures_are_recorded_and_never_their_picture(): void
    {
        $technician = $this->makeUser('teknisi@test.local', 'Admin Radio', 'Teknisi Satu');
        $this->actingAs($this->superAdmin());

        $this->postJson(route('radio-management.e-sign.store'), ['user_id' => $technician->id, 'display_name' => '', 'image' => self::drawn()])->assertOk();
        $made = $this->only(AuditLogger::ESIGN_CREATED);
        $this->assertSame(['Account' => 'teknisi@test.local', 'Name printed' => 'Teknisi Satu', 'Active' => 'Yes'], $made->new_values);
        $this->assertSame(['ItSignature', 'teknisi@test.local'], [$made->entity_type, $made->entity_label]);

        $this->putJson(route('radio-management.e-sign.update', $made->entity_id), ['display_name' => 'T. Satu', 'active' => true, 'image' => self::drawn()])->assertOk();
        $changed = $this->only(AuditLogger::ESIGN_UPDATED);
        $this->assertSame(['Name printed' => 'T. Satu', 'Signature' => 'Replaced'], $changed->new_values);

        // The form opened by its owner carries it: that is recorded against the radio.
        $radio = Radio::create(['dmr_id' => 11, 'serial_number' => '867TZB5309', 'user_name' => 'Budi Santoso']);
        $this->actingAs($technician);
        $this->get(route('radio-management.radio.checkout-form', $radio))->assertOk();
        $used = $this->only(AuditLogger::ESIGN_USED);
        $this->assertSame([(int) $technician->id, 'Radio', (int) $radio->id, ['Signature' => 'T. Satu']], [(int) $used->user_id, $used->entity_type, (int) $used->entity_id, $used->new_values]);

        $this->actingAs($this->superAdmin());
        $this->deleteJson(route('radio-management.e-sign.destroy', $made->entity_id))->assertOk();
        $this->assertSame('T. Satu', $this->only(AuditLogger::ESIGN_DELETED)->old_values['Name printed']);

        // Neither here nor in the log of requests: the stored signature is encrypted, a copy in plain text would undo that.
        $this->assertStringNotContainsString('base64', $this->everything());
        $this->assertFalse(DB::table('activity_logs')->where('data', 'like', '%base64%')->exists());
        $this->assertTrue(DB::table('activity_logs')->where('url', 'like', '%e-sign%')->exists());
    }

    // ================================================================ the trail itself

    public function test_secrets_are_redacted_and_long_values_cut(): void
    {
        $entry = app(AuditLogger::class)->log('test.redact', old: ['Password' => 'lama-sekali', 'Empty password' => null], new: [
            'Password' => 'rahasia',
            'client_secret' => 'abc',
            'Remember token' => 'xyz',
            'Picture' => 'data:image/png;base64,AAAA',
            'Note' => str_repeat('a', 600),
            'Nested' => ['api_key' => 'k', 'Kept' => 'ok'],
            'Empty password' => 'baru-sekali',
        ]);

        $this->assertSame([AuditLogger::REDACTED, AuditLogger::REDACTED, AuditLogger::REDACTED, AuditLogger::REDACTED, AuditLogger::REDACTED],
            [$entry->new_values['Password'], $entry->new_values['client_secret'], $entry->new_values['Remember token'], $entry->new_values['Picture'], $entry->new_values['Empty password']]);
        $this->assertSame(str_repeat('a', 500) . '…', $entry->new_values['Note']);
        $this->assertSame(['api_key' => AuditLogger::REDACTED, 'Kept' => 'ok'], $entry->new_values['Nested']);
        // Before: a password there was is said to be there, one there was not stays empty.
        $this->assertSame(['Password' => AuditLogger::REDACTED, 'Empty password' => null], $entry->old_values);
        foreach (['rahasia', 'lama-sekali', 'baru-sekali', 'abc', 'xyz', 'AAAA'] as $secret) {
            $this->assertStringNotContainsString('"' . $secret . '"', $this->everything());
        }

        // A record as it was made (or as it was when it went) lists the fields it had, not the empty ones.
        $made = app(AuditLogger::class)->log(AuditLogger::USER_CREATED, new: ['Name' => 'Budi', 'Directory username' => null, 'Note' => '', 'Count' => 0]);
        $this->assertSame(['Name' => 'Budi', 'Count' => 0], $made->new_values);
        $gone = app(AuditLogger::class)->log(AuditLogger::USER_DELETED, old: ['Name' => 'Budi', 'Directory username' => null]);
        $this->assertSame(['Name' => 'Budi'], $gone->old_values);
    }

    public function test_an_entry_is_neither_changed_nor_deleted(): void
    {
        $entry = app(AuditLogger::class)->log(AuditLogger::LOGOUT);

        try {
            $entry->update(['action' => 'auth.login']);
            $this->fail('An entry was changed.');
        } catch (LogicException) {
        }
        try {
            $entry->delete();
            $this->fail('An entry was deleted.');
        } catch (LogicException) {
        }
        $this->assertSame(AuditLogger::LOGOUT, DB::table('audit_logs')->where('id', $entry->id)->value('action'));
    }

    public function test_an_entry_that_cannot_be_written_does_not_stop_the_action(): void
    {
        $this->actingAs($this->superAdmin());
        $staff = $this->makeUser('staf@test.local', 'Viewer Radio');
        AuditLog::creating(fn () => throw new RuntimeException('The disk is full.'));

        $this->putJson(route('user-management.user-registered.status', $staff->id), ['active' => false])->assertOk();
        $this->assertFalse($staff->fresh()->isActive());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_before_its_migration_nothing_is_written_and_the_page_says_so(): void
    {
        Schema::drop('audit_logs');
        Cache::forget(AuditLog::READY_CACHE_KEY);
        $staff = $this->makeUser('staf@test.local', 'Viewer Radio');

        // Signing in and changing things work as before.
        $this->post('/login', ['emailusername' => 'staf@test.local', 'password' => 'secret-password'])->assertRedirect(route('dashboard.index'));
        $this->post(route('logout'));
        $this->actingAs($this->superAdmin());
        $this->putJson(route('user-management.user-registered.status', $staff->id), ['active' => false])->assertOk();

        $page = (string) $this->get(route('audit-log.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="auditNotReady"', $page);
        $this->assertStringContainsString('ready: false', $page);
        $this->getJson(route('audit-log.list'))->assertStatus(503)->assertJsonPath('message', 'The audit log is not switched on yet: its migration has not been run on this database.');
    }
}
