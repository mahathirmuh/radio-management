<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioType;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * The path in the top bar (App\Services\Breadcrumbs): the dashboard, the group and the entry of the sidebar the page
 * belongs to, and the page itself when it is deeper than its entry.
 */
class BreadcrumbsTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * The crumbs of the top bar of $url: [name, where it leads (null for none), whether it is the page that is open].
     *
     * @return list<array{0: string, 1: ?string, 2: bool}>
     */
    private function crumbs(string $url, ?User $user = null): array
    {
        $page = (string) $this->actingAs($user ?? $this->superAdmin())->get($url)->assertOk()->getContent();
        $nav = (string) strstr((string) strstr($page, '<nav class="rm-crumbbar"'), '</nav>', true);
        $this->assertNotSame('', $nav, 'no path under the top bar');
        preg_match_all('#<li>\s*(?:<i class="ti ti-chevron-right" aria-hidden="true"></i>\s*)?(?:<a href="([^"]*)">([^<]*)</a>|<span\s*(aria-current="page")?\s*>([^<]*)</span>)\s*</li>#', $nav, $found, PREG_SET_ORDER);
        $this->assertSame(substr_count($nav, '<li>'), count($found), 'a crumb that is no link and no name');

        return array_map(fn (array $crumb) => ($crumb[1] ?? '') !== ''
            ? [html_entity_decode($crumb[2]), $crumb[1], false]
            : [html_entity_decode($crumb[4]), null, ($crumb[3] ?? '') !== ''], $found);
    }

    public function test_the_dashboard_greets_where_its_path_would_be_itself_alone(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="rm-crumbbar"', $page);
        $this->assertStringContainsString('<h1 class="rm-topbar-title">Dashboard</h1>', $page);
        $this->assertStringContainsString('<p class="rm-title rm-welcome">Selamat datang di Radio Management System</p>', $page);
    }

    public function test_a_page_of_the_sidebar_comes_after_the_dashboard_and_its_group(): void
    {
        $dashboard = route('dashboard.index');

        $this->assertSame([['Dashboard', $dashboard, false], ['Register Radio', null, true]], $this->crumbs(route('radio-management.radio.index')));
        // A group has no page of its own: its name is not a link.
        $this->assertSame([['Dashboard', $dashboard, false], ['Master Data Radio', null, false], ['Tipe Radio', null, true]],
            $this->crumbs(route('radio-management.radio-type.index')));
        $this->assertSame([['Dashboard', $dashboard, false], ['User Management', null, false], ['User Terdaftar', null, true]],
            $this->crumbs(route('user-management.user-registered.index')));
    }

    public function test_the_path_is_in_the_language_of_the_page(): void
    {
        $this->withUnencryptedCookie(\App\Services\Language::COOKIE, 'en');

        $this->assertSame([['Dashboard', route('dashboard.index'), false], ['Radio Master Data', null, false], ['Radio Types', null, true]],
            $this->crumbs(route('radio-management.radio-type.index')));
        $this->assertSame([['Dashboard', route('dashboard.index'), false], ['Notifications', null, true]], $this->crumbs(route('notifications.index')));
    }

    public function test_a_page_below_its_entry_names_itself_after_the_entry_that_leads_back(): void
    {
        $radio = Radio::create(['dmr_id' => 101, 'serial_number' => '867TZB0101', 'user_name' => 'Pengguna Satu', 'radio_type_id' => RadioType::query()->value('id')]);

        $this->assertSame([
            ['Dashboard', route('dashboard.index'), false],
            ['Register Radio', route('radio-management.radio.index'), false],
            ['Radio 101', null, true],
        ], $this->crumbs(route('radio-management.radio.detail', $radio)));
    }

    public function test_a_page_outside_the_sidebar_is_the_dashboard_and_its_name(): void
    {
        $this->assertSame([['Dashboard', route('dashboard.index'), false], ['Notifikasi', null, true]], $this->crumbs(route('notifications.index')));
    }

    public function test_whoever_may_not_open_the_dashboard_does_not_get_it_in_the_path(): void
    {
        // A role with Register Radio only.
        $role = DB::table('roles')->insertGetId(['name' => 'Register Only', 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $menu = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('id');
        DB::table('menu_role')->insert(['menu_id' => $menu, 'role_id' => $role, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('menu_role_permission')->insert(['menu_id' => $menu, 'role_id' => $role, 'permission_id' => CheckMenuPermission::PERMISSION_IDS['Read'], 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([['Register Radio', null, true]], $this->crumbs(route('radio-management.radio.index'), $this->makeUser('register.only@test.local', $role)));
    }
}
