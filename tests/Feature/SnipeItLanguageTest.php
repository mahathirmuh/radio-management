<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesSnipeIt;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * The menu "Snipe-IT" in English (SnipeItTest checks it in Indonesian): the pages, the words a value of Snipe-IT is shown
 * with, and what is said when Snipe-IT gives no answer. Snipe-IT's own values (names, statuses) are shown as they are.
 */
class SnipeItLanguageTest extends TestCase
{
    use FakesSnipeIt;
    use MakesUsers;
    use RefreshDatabase;
    use UsesEnglish;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useEnglish();
    }

    public function test_the_overview_and_the_sidebar_are_in_english(): void
    {
        $this->fakeSnipeIt();
        $page = (string) $this->actingAs($this->superAdmin())->get(route('snipe-it.overview.index'))->assertOk()->getContent();

        foreach (['<h1 class="rm-topbar-title">Snipe-IT · Overview</h1>', '<span>IT Assets</span>', 'Assets by status', 'Assets by category',
            'All assets', 'See all', 'Open Snipe-IT', 'Fetched ', '2 other categories', 'People &amp; Organization', 'Licenses &amp; Stock',
            'Activity log', 'Custom fields', 'Status labels', '2,051', '23,630'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        preg_match_all('#<a class="rm-nav-link rm-nav-kid[^"]*" href="[^"]*/snipe-it[^"]*"[^>]*>\s*<span class="rm-nav-label">([^<]+)</span>#', $page, $kids);
        $this->assertSame(['Overview', 'Assets', 'Catalog', 'People &amp; Organization', 'Licenses &amp; Stock', 'History'], $kids[1]);
        foreach (['Ringkasan', 'Aset per status', 'Semua aset', 'Lihat semua', 'Buka Snipe-IT', 'Diambil', 'Katalog', 'Orang &amp; Organisasi',
            'Lisensi &amp; Stok', 'Log aktivitas', 'kategori lain', 'Aset IT'] as $part) {
            $this->assertStringNotContainsString($part, $page, $part);
        }
    }

    public function test_a_list_page_and_its_rows_are_in_english(): void
    {
        $this->fakeSnipeIt();
        $this->actingAs($this->superAdmin());

        $catalog = (string) $this->get(route('snipe-it.catalog.index', ['tab' => 'fields']))->assertOk()->getContent();
        foreach (['>Models', '>Custom fields', 'placeholder="Search custom fields…"', 'aria-label="Search"', '>Name</th>', '>Input</th>',
            '>Choices</th>', '>Unique</th>', 'data-bs-title="Fetch again from Snipe-IT"', '"loading":"Loading..."', '"open_row":"Open in Snipe-IT"'] as $part) {
            $this->assertStringContainsString($part, $catalog, $part);
        }
        foreach (['Cari ', 'Ambil ulang', 'Memuat', 'Pilihan', 'Isian'] as $part) {
            $this->assertStringNotContainsString($part, $catalog, $part);
        }
        $assets = (string) $this->get(route('snipe-it.assets.index'))->assertOk()->getContent();
        foreach (['<option value="">All categories</option>', '<option value="">All statuses</option>', '>Checked out to</th>', '>Updated</th>'] as $part) {
            $this->assertStringContainsString($part, $assets, $part);
        }

        $fields = $this->getJson(route('snipe-it.catalog.list', ['tab' => 'fields']))->assertOk()->json('data');
        $this->assertSame(['List of choices', 'Yes', 'No'], [$fields[2]['cells']['element']['t'], $fields[1]['cells']['unique']['t'], $fields[0]['cells']['unique']['t']]);
        $users = $this->getJson(route('snipe-it.people.list', ['tab' => 'users']))->assertOk()->json('data');
        $this->assertSame(['Active', 'Inactive'], [$users[0]['cells']['active']['t'], $users[1]['cells']['active']['t']]);
        $activity = $this->getJson(route('snipe-it.history.list'))->assertOk()->json('data');
        $this->assertSame(['Checkout', 'Asset', 'User'], [$activity[0]['cells']['action']['t'], $activity[0]['cells']['item']['s'], $activity[0]['cells']['target']['s']]);
        $statuses = $this->getJson(route('snipe-it.catalog.list', ['tab' => 'statuslabels']))->assertOk()->json('data');
        $this->assertSame(['Deployable', 'Undeployable', 'Archived'], array_map(fn ($row) => $row['cells']['kind']['t'], $statuses));
        $this->assertSame('Waiting for "repair"', $statuses[1]['cells']['notes']['t']);
    }

    public function test_without_an_answer_of_snipe_it_the_pages_say_why_in_english(): void
    {
        // (Not set up: nothing may be asked, and a request that is not faked fails the test.)
        config(['services.snipeit.url' => null, 'services.snipeit.token' => null]);
        $this->actingAs($this->superAdmin());

        $text ='Snipe-IT is not set up: fill in SNIPEIT_URL and SNIPEIT_TOKEN in .env.';
        $this->assertStringContainsString(e($text), (string) $this->get(route('snipe-it.overview.index'))->assertOk()->getContent());
        $this->getJson(route('snipe-it.people.list'))->assertStatus(503)->assertJsonPath('message', $text);

        $this->fakeSnipeIt(['hardware' => fn () => Http::failedConnection()]);
        $this->getJson(route('snipe-it.assets.list'))->assertStatus(503)->assertJsonPath('message', 'Snipe-IT cannot be reached. Try again in a moment.');
    }
}
