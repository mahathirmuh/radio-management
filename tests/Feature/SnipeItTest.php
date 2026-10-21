<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use Database\Seeders\SnipeItMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\SnipeIt\Services\SnipeItClient;
use Tests\Concerns\FakesSnipeIt;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The menu "Snipe-IT": what is in Snipe-IT, grouped in pages of the sidebar, read only. Snipe-IT is only ever asked (GET),
 * and of what it answers only the columns of a list reach the browser.
 */
class SnipeItTest extends TestCase
{
    use FakesSnipeIt;
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    /** @return array<string, int> route => order of the pages of the group, and 'group' => the group's place */
    private function sidebar(): array
    {
        $group = DB::table('menu')->whereNull('parent_id')->where('title', SnipeItMenuSeeder::TITLE)->first();
        $this->assertNotNull($group, 'the group');

        return ['group' => (int) $group->order]
            + DB::table('menu')->where('parent_id', $group->id)->orderBy('order')->pluck('order', 'route')->map(fn ($order) => (int) $order)->all();
    }

    public function test_the_group_comes_after_the_documents_with_its_pages_and_is_super_admins(): void
    {
        $sidebar = $this->sidebar();
        $this->assertSame(array_keys(SnipeItMenuSeeder::PAGES), array_slice(array_keys($sidebar), 1));
        $this->assertSame([1, 2, 3, 4, 5, 6], array_slice(array_values($sidebar), 1));

        $group = DB::table('menu')->whereNull('parent_id')->where('title', 'Snipe-IT')->first();
        $this->assertSame(['Aset IT', 'packages', null], [$group->section, $group->icon, $group->route]);
        $lastDocument = (int) DB::table('menu')->whereNull('parent_id')->where('section', 'Dokumen')->max('order');
        $this->assertSame($lastDocument + 1, $sidebar['group']);
        $this->assertSame($sidebar['group'] + 1, (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order'));

        // Super Admin: the group and every page, with Read (and nothing else: the pages change nothing).
        $pages = DB::table('menu')->where('parent_id', $group->id)->pluck('id');
        $this->assertSame(7, DB::table('menu_role')->where('role_id', 1)->whereIn('menu_id', $pages->push($group->id))->count());
        $this->assertSame([CheckMenuPermission::PERMISSION_IDS['Read']],
            DB::table('menu_role_permission')->where('role_id', 1)->whereIn('menu_id', $pages)->distinct()->pluck('permission_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('menu_role')->where('role_id', '!=', 1)->whereIn('menu_id', $pages)->count());

        // The sidebar: a section, the group, its pages.
        $this->fakeSnipeIt();
        $page = (string) $this->actingAs($this->superAdmin())->get(route('snipe-it.overview.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<li class="rm-nav-section" role="presentation"><span>Aset IT</span></li>\s*<li class="rm-nav-item rm-nav-group is-open has-active"#', $page);
        preg_match_all('#<a class="rm-nav-link rm-nav-kid[^"]*" href="[^"]*/snipe-it[^"]*"[^>]*>\s*<span class="rm-nav-label">([^<]+)</span>#', $page, $kids);
        $this->assertSame(['Ringkasan', 'Aset', 'Katalog', 'Orang &amp; Organisasi', 'Lisensi &amp; Stok', 'Riwayat'], $kids[1]);
        // The path: Dashboard › Snipe-IT › Ringkasan; the title in the bar.
        $this->assertStringContainsString('<h1 class="rm-topbar-title">Snipe-IT · Ringkasan</h1>', $page);
        $this->assertMatchesRegularExpression('#<li>\s*<i class="ti ti-chevron-right" aria-hidden="true"></i>\s*<span\s*>Snipe-IT</span>\s*</li>#', $page);
    }

    public function test_the_migration_adds_the_group_to_a_database_in_use_and_takes_it_away_again(): void
    {
        SnipeItMenuSeeder::remove();
        $this->assertFalse(DB::table('menu')->where('title', 'Snipe-IT')->exists());
        $users = (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order');
        $migration = require database_path('migrations/2026_10_21_110000_add_snipe_it_menu.php');

        $migration->up();
        $this->assertSame($users, $this->sidebar()['group']);
        $this->assertSame($users + 1, (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order'));
        // Once there, it stays as the owner leaves it.
        $migration->up();
        $this->assertSame(1, DB::table('menu')->where('title', 'Snipe-IT')->count());
        $this->assertSame(6, DB::table('menu')->where('route', 'like', 'snipe-it.%')->count());

        $migration->down();
        $this->assertFalse(DB::table('menu')->where('title', 'Snipe-IT')->exists());
        $this->assertFalse(DB::table('menu')->where('route', 'like', 'snipe-it.%')->exists());
        $this->assertSame($users, (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order'));
        $this->assertSame(0, DB::table('menu_role')->whereNotIn('menu_id', DB::table('menu')->pluck('id'))->count());
    }

    public function test_without_the_menu_the_pages_are_closed_to_everyone_but_super_admin(): void
    {
        SnipeItMenuSeeder::remove();
        Cache::forget('rbac_menu_route_key_map');
        $this->fakeSnipeIt();

        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->get(route('snipe-it.overview.index'))->assertForbidden();
        $this->get(route('snipe-it.assets.index'))->assertForbidden();
        $this->getJson(route('snipe-it.assets.list'))->assertForbidden();

        $this->actingAs($this->superAdmin());
        $this->get(route('snipe-it.assets.index'))->assertOk();
    }

    public function test_not_set_up_the_pages_say_so_and_ask_nothing(): void
    {
        Http::fake();
        config(['services.snipeit.url' => null, 'services.snipeit.token' => null]);
        $this->actingAs($this->superAdmin());

        $page = (string) $this->get(route('snipe-it.overview.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Snipe-IT belum diatur: isi SNIPEIT_URL dan SNIPEIT_TOKEN di .env.', $page);
        // The groups are there, without numbers.
        $this->assertMatchesRegularExpression('#<span class="snipe-name">Model</span>\s*<span class="snipe-num">—</span>#', $page);
        $this->assertStringContainsString('Snipe-IT belum diatur', (string) $this->get(route('snipe-it.catalog.index'))->assertOk()->getContent());
        $this->getJson(route('snipe-it.assets.list'))->assertStatus(503)
            ->assertJsonPath('message', 'Snipe-IT belum diatur: isi SNIPEIT_URL dan SNIPEIT_TOKEN di .env.')
            ->assertJsonPath('reason', 'not_configured');
        Http::assertNothingSent();
    }

    public function test_the_overview_counts_every_list_and_the_assets_by_status_and_category(): void
    {
        $this->fakeSnipeIt();
        $page = (string) $this->actingAs($this->superAdmin())->get(route('snipe-it.overview.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Snipe-IT v6.2.4-pre', $page);
        $this->assertMatchesRegularExpression('#Diambil \d{2}-\d{2}-\d{4} \d{2}:\d{2}#', $page);
        $this->assertStringContainsString('<a class="btn-rm secondary" href="http://snipeit.test" target="_blank" rel="noopener noreferrer">', $page);
        // Every asset, then each status with its colour, leading to the assets of that status.
        $assets = route('snipe-it.assets.index');
        $this->assertMatchesRegularExpression('#<a class="snipe-row is-total" href="' . preg_quote($assets, '#') . '">\s*<span class="snipe-name">Semua aset</span>\s*<span class="snipe-num">539</span>#', $page);
        $this->assertMatchesRegularExpression('#href="' . preg_quote($assets, '#') . '\?status=4">\s*<span class="snipe-name"><span class="rm-badge green">Deployed</span></span>\s*<span class="snipe-num">2\.051</span>#', $page);
        $this->assertStringContainsString('<span class="rm-badge red">Broken</span>', $page);
        // The eight biggest categories, the others counted together (a category without assets is no category of assets).
        preg_match_all('#href="' . preg_quote($assets, '#') . '\?category=(\d+)">\s*<span class="snipe-name">([^<]+)</span>\s*<span class="snipe-num">([^<]+)</span>#', $page, $categories);
        $this->assertSame(['HT', 'Notebook', 'CCTV', 'Monitor', 'PC Desktop', 'SIM card', 'Switch', 'Printer'], $categories[2]);
        $this->assertSame(['539', '462', '418', '252', '127', '117', '103', '48'], $categories[3]);
        $this->assertStringContainsString('2 kategori lain', $page);
        $this->assertStringNotContainsString('Empty', $page);
        // The groups, each list with how many there are, leading to its tab.
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('snipe-it.catalog.index', ['tab' => 'models']), '#') . '">\s*<span class="snipe-name">Model</span>\s*<span class="snipe-num">190</span>#', $page);
        $this->assertMatchesRegularExpression('#<span class="snipe-name">User</span>\s*<span class="snipe-num">2\.085</span>#', $page);
        $this->assertMatchesRegularExpression('#<span class="snipe-name">Log aktivitas</span>\s*<span class="snipe-num">23\.630</span>#', $page);
        $this->assertMatchesRegularExpression('#<span class="snipe-name">Kit</span>\s*<span class="snipe-num">0</span>#', $page);

        // Asked all at once, only ever with GET, with the token; which is nowhere in the page.
        $this->assertCount(24, $this->snipeAsked);
        $this->assertSame(['GET'], array_values(array_unique(array_column($this->snipeAsked, 'method'))));
        $this->assertSame(['Bearer ' . self::SNIPE_TOKEN], array_values(array_unique(array_column($this->snipeAsked, 'token'))));
        $this->assertStringNotContainsString(self::SNIPE_TOKEN, $page);

        // Kept: opening it again asks nothing; "Refresh" asks again, and goes back to the page's own address.
        $this->get(route('snipe-it.overview.index'))->assertOk();
        $this->assertCount(24, $this->snipeAsked);
        $this->get(route('snipe-it.overview.index', ['fresh' => 1]))->assertRedirect(route('snipe-it.overview.index'));
        $this->assertCount(48, $this->snipeAsked);
    }

    public function test_a_list_sends_only_its_columns_as_plain_text_with_the_pages_of_snipe_it(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $answer = $this->getJson(route('snipe-it.assets.list'))->assertOk()->json();
        $this->assertSame([['limit' => '25', 'offset' => '0', 'sort' => 'updated_at', 'order' => 'desc']], array_column($this->snipeAskedAbout('hardware'), 'query'));
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 539, 'last_page' => 22, 'from' => 1, 'to' => 2, 'total_text' => '539'], $answer['meta']);
        $this->assertSame(['key' => 'updated', 'order' => 'desc'], $answer['sort']);

        [$first, $second] = $answer['data'];
        $this->assertSame(['id', 'link', 'inside', 'image', 'icon', 'cells'], array_keys($first));
        $this->assertSame(11, $first['id']);
        // An asset leads to its detail here; Snipe-IT's own page is a button there.
        $this->assertSame([route('snipe-it.assets.show', 11), true], [$first['link'], $first['inside']]);
        $this->assertSame(['asset_tag', 'serial', 'model', 'category', 'status', 'holder', 'location', 'updated', 'open'], array_keys($first['cells']));
        // Snipe-IT's picture (the browser loads it from Snipe-IT), and the icon of the category for a row without one.
        $this->assertSame(['https://snipeit.test/uploads/models/p6620i.png', 'radio'], [$first['image'], $first['icon']]);
        $this->assertSame([null, 'radio'], [$second['image'], $second['icon']]);
        $this->assertSame(['t' => null, 's' => null, 'tone' => null], $first['cells']['open']);
        // Snipe-IT's escaped texts as plain text (the page escapes them again), without markup.
        $this->assertSame(['t' => 'MTI-HT-001', 's' => 'Radio one', 'tone' => null], $first['cells']['asset_tag']);
        $this->assertSame(['t' => "Ann O'Neil", 's' => 'User', 'tone' => null], $first['cells']['holder']);
        $this->assertSame(['t' => 'Warehouse & Logistics', 's' => null, 'tone' => null], $first['cells']['location']);
        $this->assertSame(['t' => 'Deployed', 's' => null, 'tone' => 'blue'], $first['cells']['status']);
        $this->assertSame('21-10-2026 09:15', $first['cells']['updated']['t']);
        // Nothing becomes "nothing": no serial, no holder, no name.
        $this->assertSame([null, null, null], [$second['cells']['serial']['t'], $second['cells']['holder']['t'], $second['cells']['asset_tag']['s']]);
        $this->assertSame('green', $second['cells']['status']['tone']);

        // What is not a column does not leave the server.
        $json = json_encode($answer);
        foreach (['1,234.00', '4021', 'Battery SN', 'ann@example.test', 'custom_fields'] as $private) {
            $this->assertStringNotContainsString($private, $json, $private);
        }
    }

    public function test_search_filters_order_and_page_go_to_snipe_it_and_nothing_else(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $this->getJson(route('snipe-it.assets.list', ['search' => ' HT ', 'category' => '15', 'status' => '4', 'sort' => 'asset_tag', 'order' => 'asc', 'page' => 3, 'paginated' => 50]))
            ->assertOk()->assertJsonPath('meta.from', 101)->assertJsonPath('meta.last_page', 11)->assertJsonPath('sort', ['key' => 'asset_tag', 'order' => 'asc']);
        $this->assertSame(['limit' => '50', 'offset' => '100', 'search' => 'HT', 'sort' => 'asset_tag', 'order' => 'asc', 'category_id' => '15', 'status_id' => '4'],
            $this->snipeAskedAbout('hardware')[0]['query']);

        // What is no column, no number, no size of a page or no list of the page counts as nothing.
        $this->getJson(route('snipe-it.assets.list', ['sort' => 'purchase_cost', 'order' => 'sideways', 'category' => 'x;1', 'status' => '-4', 'paginated' => 1000, 'page' => 'x', 'tab' => '../users']))
            ->assertOk();
        $this->assertSame(['limit' => '25', 'offset' => '0', 'sort' => 'updated_at', 'order' => 'desc'], $this->snipeAskedAbout('hardware')[1]['query']);
        // (Lists where one word is expected are nothing too: the same question as the one before, so its kept answer.)
        $this->getJson(route('snipe-it.assets.list', ['search' => ['a'], 'category' => ['1']]))->assertOk();
        $this->assertCount(2, $this->snipeAskedAbout('hardware'));

        // A tab of the page; a filter of another list is not sent.
        $this->getJson(route('snipe-it.people.list', ['tab' => 'users', 'sort' => 'name', 'order' => 'desc', 'category' => '15']))->assertOk();
        $this->assertSame(['limit' => '25', 'offset' => '0', 'sort' => 'first_name', 'order' => 'desc'], $this->snipeAskedAbout('users')[0]['query']);
        $this->assertSame(['GET'], array_values(array_unique(array_column($this->snipeAsked, 'method'))));
    }

    public function test_a_licence_key_a_phone_number_and_an_address_never_leave_the_server(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $licenses = json_encode($this->getJson(route('snipe-it.stock.list', ['tab' => 'licenses']))->assertOk()->json());
        $this->assertStringNotContainsString('SECRET-LICENSE-KEY-9999', $licenses);
        $this->assertStringNotContainsString('licensee@example.test', $licenses);
        $this->assertStringContainsString('"t":"Office Suite"', $licenses);
        $this->assertStringContainsString('"t":"02-01-2026"', $licenses);

        $users = $this->getJson(route('snipe-it.people.list', ['tab' => 'users']))->assertOk()->json();
        $this->assertStringNotContainsString('PRIVATE-PHONE', json_encode($users));
        $this->assertStringNotContainsString('Private Street', json_encode($users));
        $this->assertSame(['t' => "Ann O'Neil", 's' => 'ann@example.test', 'tone' => null], $users['data'][0]['cells']['name']);
        $this->assertSame(['t' => 'Aktif', 's' => null, 'tone' => 'green'], $users['data'][0]['cells']['active']);
        $this->assertSame(['t' => 'Nonaktif', 's' => null, 'tone' => 'gray'], $users['data'][1]['cells']['active']);
        $this->assertSame(['http://snipeit.test/users/7', false], [$users['data'][0]['link'], $users['data'][0]['inside']]);
    }

    public function test_a_list_snipe_it_gives_whole_is_searched_and_paged_here(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $all = $this->getJson(route('snipe-it.catalog.list', ['tab' => 'fields', 'paginated' => 10]))->assertOk()->json();
        $this->assertSame(['MAC Address', 'Radio ID', 'Charger HT'], array_map(fn ($row) => $row['cells']['name']['t'], $all['data']));
        $this->assertSame(['t' => 'Yes, No', 's' => null, 'tone' => null], $all['data'][2]['cells']['choices']);
        $this->assertSame(['t' => 'Daftar pilihan', 's' => null, 'tone' => null], $all['data'][2]['cells']['element']);
        $this->assertSame(['t' => 'Ya', 's' => null, 'tone' => 'green'], $all['data'][1]['cells']['unique']);
        $this->assertNull($all['data'][0]['link']);

        $found = $this->getJson(route('snipe-it.catalog.list', ['tab' => 'fields', 'search' => 'radio']))->assertOk()->json();
        $this->assertSame(['Radio ID'], array_map(fn ($row) => $row['cells']['name']['t'], $found['data']));
        $this->assertSame(1, $found['meta']['total']);
        // Snipe-IT was asked for the whole list each time (it takes no page and no search), then kept.
        $this->assertSame([[]], array_column($this->snipeAskedAbout('fields'), 'query'));
    }

    public function test_the_activity_log_tells_the_action_the_item_and_what_changed(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $answer = $this->getJson(route('snipe-it.history.list', ['action' => 'checkin from']))->assertOk()->json();
        $this->assertSame(['limit' => '25', 'offset' => '0', 'sort' => 'created_at', 'order' => 'desc', 'action_type' => 'checkin from'], $this->snipeAskedAbout('reports/activity')[0]['query']);
        [$checkout, $other] = $answer['data'];
        $this->assertSame(['t' => 'Checkout', 's' => null, 'tone' => 'blue'], $checkout['cells']['action']);
        $this->assertSame(['t' => 'MTI-HT-001', 's' => 'Aset', 'tone' => null], $checkout['cells']['item']);
        $this->assertSame([route('snipe-it.assets.show', 11), true], [$checkout['link'], $checkout['inside']]);
        $this->assertSame(['t' => "Ann O'Neil", 's' => 'User', 'tone' => null], $checkout['cells']['target']);
        // The field by its name, the status by its name (from Snipe-IT's list of statuses).
        $this->assertSame('Status: Ready to Deploy → Deployed', $checkout['cells']['changes']['t']);
        $this->assertSame('Battery swapped', $checkout['cells']['note']['t']);
        $this->assertSame('21-10-2026 10:30', $checkout['cells']['time']['t']);
        // An action and a kind of item this page has no word for: as Snipe-IT names them, grey, without a page.
        $this->assertSame(['t' => 'restored', 's' => null, 'tone' => 'gray'], $other['cells']['action']);
        $this->assertSame(['t' => 'Something', 's' => 'gadget', 'tone' => null], $other['cells']['item']);
        $this->assertNull($other['link']);

        // An action that is none of the filter's is not sent.
        $this->getJson(route('snipe-it.history.list', ['action' => 'delete everything']))->assertOk();
        $this->assertArrayNotHasKey('action_type', $this->snipeAskedAbout('reports/activity')[1]['query']);
    }

    public function test_answers_are_kept_and_refresh_asks_again(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $this->getJson(route('snipe-it.catalog.list', ['tab' => 'models']))->assertOk();
        $this->getJson(route('snipe-it.catalog.list', ['tab' => 'models']))->assertOk();
        $this->assertCount(1, $this->snipeAskedAbout('models'));
        $this->getJson(route('snipe-it.catalog.list', ['tab' => 'models', 'fresh' => 1]))->assertOk();
        $this->assertCount(2, $this->snipeAskedAbout('models'));
        // Another page is another question.
        $this->getJson(route('snipe-it.catalog.list', ['tab' => 'models', 'page' => 2]))->assertOk();
        $this->assertCount(3, $this->snipeAskedAbout('models'));
    }

    public function test_snipe_it_out_of_reach_or_refusing_the_token_is_said_plainly(): void
    {
        $this->fakeSnipeIt(['hardware' => fn () => Http::failedConnection('cURL error 7: Failed to connect to snipeit.test port 80')]);
        $this->actingAs($this->superAdmin());

        $down = $this->getJson(route('snipe-it.assets.list'))->assertStatus(503)
            ->assertJsonPath('message', 'Snipe-IT tidak bisa dihubungi. Coba lagi sebentar lagi.')->assertJsonPath('reason', 'unreachable');
        $this->assertStringNotContainsString('snipeit.test', json_encode($down->json()));
        // For a minute it is not asked again (each page would wait for the time limit first); "Refresh" asks.
        $this->getJson(route('snipe-it.assets.list', ['page' => 2]))->assertStatus(503);
        $this->assertCount(1, $this->snipeAskedAbout('hardware'));
        $this->getJson(route('snipe-it.assets.list', ['fresh' => 1]))->assertStatus(503);
        $this->assertCount(2, $this->snipeAskedAbout('hardware'));
        Cache::forget(SnipeItClient::DOWN_KEY);

        // A token Snipe-IT refuses: every question of the overview comes back refused.
        $this->fakeSnipeIt(array_fill_keys(['hardware', 'models', 'version', 'statuslabels', 'categories'], fn () => Http::response(['status' => 'error', 'message' => 'Unauthorized.'], 401)));
        $this->getJson(route('snipe-it.assets.list'))->assertStatus(503)
            ->assertJsonPath('message', 'Snipe-IT menolak token-nya (401). Periksa SNIPEIT_TOKEN di .env.');
        $this->fakeSnipeIt(['models' => ['status' => 'error', 'messages' => 'Something went wrong']]);
        $this->getJson(route('snipe-it.catalog.list'))->assertStatus(503)
            ->assertJsonPath('message', 'Snipe-IT menjawab dengan kesalahan (200). Coba lagi nanti.');
    }

    public function test_a_page_has_a_tab_per_list_with_its_number_and_the_filters_of_its_list(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $catalog = (string) $this->get(route('snipe-it.catalog.index', ['tab' => 'fields']))->assertOk()->getContent();
        preg_match_all('#<a href="[^"]*/snipe-it/catalog\?tab=(\w+)"[^>]*>([^<]+)#', $catalog, $tabs);
        $this->assertSame(['models', 'categories', 'manufacturers', 'statuslabels', 'suppliers', 'fields', 'fieldsets', 'depreciations'], $tabs[1]);
        $this->assertSame('Custom field', trim($tabs[2][5]));
        $this->assertMatchesRegularExpression('#class="is-on"\s+aria-current="page"\s*>Custom field\s*<span class="rm-tab-count">3</span>#', $catalog);
        preg_match_all('#<th scope="col" data-key="(\w+)"#', $catalog, $heads);
        $this->assertSame(['name', 'element', 'format', 'choices', 'unique'], $heads[1]);
        $this->assertStringContainsString('placeholder="Cari custom field…"', $catalog);
        $this->assertStringContainsString('<h1 class="rm-topbar-title">Snipe-IT · Katalog</h1>', $catalog);

        // The assets: no tabs, a filter by category and one by status, set by the address; the picture with the tag, a button to the detail.
        $assets = (string) $this->get(route('snipe-it.assets.index', ['category' => 101, 'q' => 'P6620i']))->assertOk()->getContent();
        preg_match_all('#<th scope="col" data-key="(\w+)"#', $assets, $assetHeads);
        $this->assertSame(['asset_tag', 'serial', 'model', 'category', 'status', 'holder', 'location', 'updated', 'open'], $assetHeads[1]);
        $this->assertStringContainsString('<th scope="col" data-key="open" class="text-end rm-sticky-end"><span class="visually-hidden">Detail</span></th>', $assets);
        $this->assertStringNotContainsString('class="rm-tabs"', $assets);
        $this->assertStringContainsString('<option value="101" selected>HT (539)</option>', $assets);
        $this->assertMatchesRegularExpression('#<option value="4"\s*>Deployed \(2\.051\)</option>#', $assets);
        $this->assertStringContainsString('<option value="">Semua status</option>', $assets);
        $this->assertStringContainsString('value="P6620i"', $assets);
        $this->assertStringContainsString('filters: {"category":"101","status":""}', $assets);
        $this->assertStringContainsString('sort: "updated",', $assets);

        // The activity log: a filter by action, by its words.
        $history = (string) $this->get(route('snipe-it.history.index', ['action' => 'checkout']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<option value="checkin from"\s*>Checkin</option>#', $history);
        $this->assertStringContainsString('<option value="checkout" selected>Checkout</option>', $history);
        $this->assertStringContainsString('filters: {"action":"checkout"}', $history);
    }

    public function test_an_asset_has_a_page_with_everything_snipe_it_says_about_it(): void
    {
        $this->fakeSnipeIt(self::snipeAssetAnswers());
        $page = (string) $this->actingAs($this->superAdmin())->get(route('snipe-it.assets.show', 11))->assertOk()->getContent();

        // The title in the bar, the path back to the list of assets.
        $this->assertStringContainsString('<h1 class="rm-topbar-title">Snipe-IT · Detail Aset</h1>', $page);
        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(route('snipe-it.assets.index'), '#') . '">Aset</a>\s*</li>\s*<li>\s*<i class="ti ti-chevron-right" aria-hidden="true"></i>\s*<span\s+aria-current="page"\s*>MTI-HT-001</span>#', $page);
        // The head: the tag, the name (its markup taken away), the serial, the model, the status, Snipe-IT's picture and page.
        $this->assertMatchesRegularExpression('#<div class="rm-hero-id"><span>MTI-HT-001</span><span class="snipe-hero-name">Radio one</span></div>#', $page);
        $this->assertStringContainsString('<span class="snipe-mono">SN 867TBT0001</span>', $page);
        $this->assertStringContainsString('<span class="rm-badge blue">Deployed</span>', $page);
        $this->assertStringContainsString('<img src="https://snipeit.test/uploads/models/p6620i.png" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">', $page);
        // The picture large too, beside the history; a click opens it whole.
        $this->assertMatchesRegularExpression('#<h2 class="rm-cardbox-title" id="snipePicture">Gambar</h2>\s*<a class="rm-cardbox-link" href="https://snipeit.test/uploads/models/p6620i.png" target="_blank" rel="noopener noreferrer">Buka ukuran penuh</a>#', $page);
        $this->assertMatchesRegularExpression('#<a class="snipe-picture" href="https://snipeit.test/uploads/models/p6620i.png" target="_blank" rel="noopener noreferrer"><img src="https://snipeit.test/uploads/models/p6620i.png"\s+alt="Motorola XiR P6620i"#', $page);
        $this->assertStringContainsString('<a class="btn-rm secondary" href="http://snipeit.test/hardware/11" target="_blank" rel="noopener noreferrer">', $page);

        // The facts, each by its name.
        $fact = fn (string $label) => preg_match('#<dt>' . preg_quote($label, '#') . '</dt>\s*<dd[^>]*>(.*?)</dd>#s', $page, $found) ? trim(preg_replace('/\s+/', ' ', strip_tags($found[1]))) : null;
        $this->assertSame("Ann O&#039;Neil User · ann · ann@example.test", $fact('Pemegang'));
        $this->assertMatchesRegularExpression('#<a class="snipe-link" href="http://snipeit.test/users/7"\s+target="_blank" rel="noopener noreferrer"\s*>Ann O&\#039;Neil</a>#', $page);
        $this->assertSame('Warehouse &amp; Logistics', $fact('Lokasi'));
        $this->assertSame('IT Store', $fact('Lokasi default'));
        $this->assertSame('21-10-2026 10:30', $fact('Checkout terakhir'));
        $this->assertSame('3 kali checkout · 2 kali checkin', $fact('Dipinjamkan'));
        $this->assertSame('Motorola XiR P6620i XIR P6620i', $fact('Model'));
        $this->assertSame('Radio Supplier', $fact('Supplier'));
        $this->assertSame('PO-47841', $fact('No. order'));
        $this->assertSame('07-10-2024', $fact('Tanggal beli'));
        $this->assertSame('1,234.00', $fact('Harga beli'));
        $this->assertSame('987.00', $fact('Nilai buku'));
        $this->assertSame('12 bulan · sampai 02-01-2027', $fact('Garansi'));
        $this->assertMatchesRegularExpression('/^\d+ tahun/', (string) $fact('Umur'));
        $this->assertSame('—', $fact('Akhir masa pakai'));
        $this->assertSame('07-10-2027', $fact('Audit berikutnya'));
        // The custom fields of the model; one whose name says it is a secret is not shown.
        $this->assertSame('4021', $fact('Radio ID'));
        $this->assertSame('—', $fact('MDC Code'));
        $this->assertSame('Disembunyikan', $fact('BIOS Password'));
        $this->assertStringNotContainsString('bios-secret-123', $page);
        // The notes keep their lines, without markup.
        $this->assertStringContainsString('<div class="rm-cardbox-body snipe-text">NEW RADIO' . "\n" . 'Battery SN 50002F1488A5 alert(1)</div>', $page);

        // The history, newest first: who got it, the changes in words (a password never), the note without its markup.
        preg_match_all('#<div class="rm-timeline-title">\s*<span>([^<]+)</span>#', $page, $actions);
        $this->assertSame(['Checkout', 'Diubah', 'Dibuat'], $actions[1]);
        $this->assertStringContainsString('<span class="snipe-sub">ke Ann O&#039;Neil (User)</span>', $page);
        $this->assertStringContainsString('<div class="rm-timeline-meta">21-10-2026 10:30 · oleh Admin One</div>', $page);
        $this->assertStringContainsString('<div class="rm-timeline-text snipe-text">Handed over at the gate</div>', $page);
        preg_match_all('#<span class="rm-change-field">([^<]*)</span>\s*<span class="rm-change-old">([^<]*)</span>\s*<i class="ti ti-arrow-right" aria-hidden="true"></i>\s*<span class="rm-change-new">([^<]*)</span>#', $page, $changes, PREG_SET_ORDER);
        $this->assertSame([
            ['Status', 'Ready to Deploy', 'Deployed'],
            ['Radio ID', '', '4021'],
            ['Lokasi', 'Warehouse &amp; Logistics', 'IT Store'],
            ['Password', '••••••', '••••••'],
        ], array_map(fn ($change) => array_slice($change, 1), $changes));
        $this->assertStringNotContainsString('new-secret', $page);
        $this->assertStringContainsString('3 kejadian, terbaru di atas', $page);
        $this->assertStringNotContainsString('Selebihnya ada di Snipe-IT', $page);
        // Its maintenance, the licence by its name (never its key), what is checked out to it (a page here too).
        $this->assertStringContainsString('<div class="snipe-list-title">Battery swap</div>', $page);
        $this->assertMatchesRegularExpression('#Repair · 10-09-2026 – 12-09-2026 · Radio Supplier · Biaya 350,000.00\s*</div>#', $page);
        $this->assertStringContainsString('Old battery &amp; charger checked', $page);
        $this->assertStringContainsString('>Radio Programming Software</a>', $page);
        $this->assertStringNotContainsString('SECRET-PROGRAMMING-KEY', $page);
        $this->assertStringContainsString('<a class="snipe-link" href="' . route('snipe-it.assets.show', 99) . '">MTI-HS-099</a>', $page);

        // What was asked: the asset, its history, maintenances, licences and what is checked out to it, all at once; GET only.
        $this->assertSame([[]], array_column($this->snipeAskedAbout('hardware/11'), 'query'));
        $this->assertSame(['item_type' => 'asset', 'item_id' => '11', 'limit' => '100', 'sort' => 'created_at', 'order' => 'desc'], $this->snipeAskedAbout('reports/activity')[0]['query']);
        $this->assertSame(['asset_id' => '11', 'limit' => '50', 'sort' => 'start_date', 'order' => 'desc'], $this->snipeAskedAbout('maintenances')[0]['query']);
        $this->assertCount(1, $this->snipeAskedAbout('hardware/11/licenses'));
        $this->assertSame(['assigned_to' => '11', 'assigned_type' => 'App\\Models\\Asset', 'limit' => '50'], $this->snipeAskedAbout('hardware')[0]['query']);
        $this->assertSame(['GET'], array_values(array_unique(array_column($this->snipeAsked, 'method'))));
        $this->assertStringNotContainsString(self::SNIPE_TOKEN, $page);

        // Kept: opening it again asks nothing; "Refresh" asks again and goes back to the page's own address.
        $asked = count($this->snipeAsked);
        $this->get(route('snipe-it.assets.show', 11))->assertOk();
        $this->assertCount($asked, $this->snipeAsked);
        $this->get(route('snipe-it.assets.show', ['asset' => 11, 'fresh' => 1]))->assertRedirect(route('snipe-it.assets.show', 11));
        $this->assertCount(2, $this->snipeAskedAbout('hardware/11'));
    }

    public function test_a_long_history_says_where_the_rest_is_and_an_asset_snipe_it_does_not_have_is_not_found(): void
    {
        $this->fakeSnipeIt(self::snipeAssetAnswers(150));
        $this->actingAs($this->superAdmin());

        $page = (string) $this->get(route('snipe-it.assets.show', 11))->assertOk()->getContent();
        $this->assertStringContainsString('150 kejadian, terbaru di atas', $page);
        $this->assertStringContainsString('Menampilkan 3 kejadian terbaru dari 150. Selebihnya ada di Snipe-IT.', $page);

        $this->get(route('snipe-it.assets.show', 99))->assertNotFound();
        $this->get('/snipe-it/assets/abc')->assertNotFound();
    }

    public function test_the_detail_of_an_asset_needs_read_on_the_page_of_the_assets(): void
    {
        $this->fakeSnipeIt(self::snipeAssetAnswers());
        $viewer = $this->makeUser('viewer@test.local', 'Viewer Radio');
        $role = (int) DB::table('roles')->where('name', 'Viewer Radio')->value('id');
        $assets = (int) DB::table('menu')->where('route', 'snipe-it.assets.index')->value('id');
        $group = (int) DB::table('menu')->where('title', 'Snipe-IT')->whereNull('parent_id')->value('id');
        foreach ([$group, $assets] as $menu) {
            DB::table('menu_role')->insert(['menu_id' => $menu, 'role_id' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($viewer);

        // The page of the list opens with the menu alone; its rows and the detail of an asset need Read.
        $this->get(route('snipe-it.assets.index'))->assertOk();
        $this->get(route('snipe-it.assets.show', 11))->assertForbidden();
        $this->getJson(route('snipe-it.assets.list'))->assertForbidden();
        DB::table('menu_role_permission')->insert(['menu_id' => $assets, 'role_id' => $role, 'permission_id' => CheckMenuPermission::PERMISSION_IDS['Read'], 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('snipe-it.assets.show', 11))->assertOk();
        // (Another page of the group is not given with it.)
        $this->get(route('snipe-it.people.index'))->assertForbidden();
    }

    public function test_the_detail_of_an_asset_says_so_when_snipe_it_gives_no_answer(): void
    {
        $this->fakeSnipeIt(['hardware/11' => fn () => Http::failedConnection(), 'reports/activity' => fn () => Http::failedConnection(),
            'maintenances' => fn () => Http::failedConnection(), 'hardware/11/licenses' => fn () => Http::failedConnection(), 'hardware' => fn () => Http::failedConnection()]);
        $page = (string) $this->actingAs($this->superAdmin())->get(route('snipe-it.assets.show', 11))->assertOk()->getContent();
        $this->assertStringContainsString('Snipe-IT tidak bisa dihubungi. Coba lagi sebentar lagi.', $page);
        $this->assertStringNotContainsString('class="rm-hero"', $page);
    }

    public function test_the_page_script_draws_the_list_and_sends_what_is_chosen(): void
    {
        $this->fakeSnipeIt();
        $this->checkPageScript(route('snipe-it.assets.index'), 'tests/tools/snipe-it-behaviour.mjs', 8);
    }
}
