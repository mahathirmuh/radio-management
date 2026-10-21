<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioHistory;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * The radio dashboard in both languages: its words, the names of what the register keeps (placements, conditions,
 * checks, kinds of history lines) and its numbers follow the page, while the API shows the same figures with the names
 * as they are stored.
 */
class DashboardLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;
    use UsesEnglish;

    protected bool $seed = true;

    /** Five active radios without a type or a company (3 good, 1 broken, 1 not checked) and a handover by nobody. */
    private function register(): void
    {
        foreach (['layak', 'layak', 'layak', 'rusak', 'belum_dicek'] as $i => $condition) {
            $radio = Radio::create(['dmr_id' => 30 + $i, 'serial_number' => '867TZB540' . $i, 'user_name' => 'Pengguna ' . $i, 'condition' => $condition]);
        }
        RadioHistory::create(['radio_id' => $radio->id, 'event_date' => now()->toDateString(), 'action' => 'Serah Terima', 'description' => 'Diserahkan ke Budi']);
    }

    public function test_the_dashboard_in_english(): void
    {
        $this->register();
        $this->useEnglish();
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1 class="rm-topbar-title">Dashboard</h1>', $page);
        $this->assertStringContainsString('<p class="rm-title rm-welcome">Welcome to Radio Management System</p>', $page);
        $this->assertStringContainsString('<span class="rm-kpi-label">Active radios</span>', $page);
        $this->assertStringContainsString('<span class="rm-kpi-label">With user</span>', $page);
        $this->assertStringContainsString('<span class="rm-kpi-label">Archived</span>', $page);
        $this->assertStringContainsString('<div class="rm-cardbox-sub">5 radios, by the last condition recorded</div>', $page);

        // The pie: the names of the conditions in English, the shares written the English way.
        $this->assertStringContainsString('aria-label="Condition of active radios: Good 3, Needs service 0, Broken 1, Not checked 1"', $page);
        preg_match_all('#class="viz-slice" tabindex="-1"\s+data-viz-key="[a-z_]+"\s+data-viz-tip="([^"]*)">#', $page, $slices);
        $this->assertSame(['Good: 3 (60.0%)', 'Broken: 1 (20.0%)', 'Not checked: 1 (20.0%)'], $slices[1]);
        // The ring of the companies: the total and its unit in English, the subtitle with the number.
        $this->assertStringContainsString('<text class="viz-donut-unit" x="74" y="90">radios</text>', $page);
        $this->assertStringContainsString('Units and share of 5, most first', $page);
        $this->assertStringContainsString('aria-label="Show radios: Good"', $page);

        // What needs action, the bars (the radios without a company or a type), the recent activity.
        $this->assertStringContainsString('<h2 class="rm-cardbox-title">Needs action</h2>', $page);
        $this->assertStringContainsString('<span class="rm-badge orange">Radio type empty</span>', $page);
        $this->assertStringContainsString('class="rm-attn-link">Show</a>', $page);
        $this->assertStringContainsString('data-viz-tip="(none): 5 radios (100.0%)"', $page);
        $this->assertStringContainsString('<span class="flex-fill">(none)</span>', $page);
        $this->assertStringContainsString('<span class="viz-col-label">(none)</span>', $page);
        $this->assertStringContainsString('<h2 class="rm-cardbox-title">Recent activity</h2>', $page);
        $this->assertStringContainsString('<span class="rm-badge blue">Handover</span>', $page);
        $this->assertStringContainsString('<th class="text-end">By</th>', $page);
        $this->assertStringContainsString('<td class="text-nowrap text-end radio-muted">System</td>', $page);
        // What people wrote stays as it was written.
        $this->assertStringContainsString('Diserahkan ke Budi', $page);

        foreach (['Selamat datang di Radio Management System', 'Radio aktif', 'Kondisi radio aktif', 'Radio ID bebas berikutnya', 'Perlu tindakan', 'Buka inbox',
            'Aktivitas terbaru', 'Semua riwayat', 'Lihat radio', '>Lihat<', '(kosong)', '60,0%', 'rm-badge blue">Serah Terima<',
            'radio-muted">Sistem<', 'Tipe radio kosong'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $page);
        }
    }

    public function test_the_dashboard_in_indonesian_reads_as_before(): void
    {
        $this->register();
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<p class="rm-title rm-welcome">Selamat datang di Radio Management System</p>', $page);
        $this->assertStringContainsString('<div class="rm-cardbox-sub">5 radio, menurut kondisi terakhir yang tercatat</div>', $page);
        $this->assertStringContainsString('aria-label="Lihat radio Layak"', $page);
        $this->assertStringContainsString('<span class="rm-badge orange">Tipe radio kosong</span>', $page);
        $this->assertStringContainsString('data-viz-tip="(kosong): 5 radio (100,0%)"', $page);
        $this->assertStringContainsString('<span class="rm-badge blue">Serah Terima</span>', $page);
        $this->assertStringContainsString('<td class="text-nowrap text-end radio-muted">Sistem</td>', $page);
    }

    public function test_an_empty_register_in_english(): void
    {
        $this->useEnglish();
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="rm-empty-title">No radio data yet</div>', $page);
        $this->assertStringContainsString('Import the old register from Excel, or add radios one by one in Register Radio.', $page);
        $this->assertStringNotContainsString('Belum ada data radio', $page);
    }

    public function test_the_api_keeps_the_names_as_they_are_stored(): void
    {
        $this->register();
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'), write: false);

        // English is the language of the API, but its figures name things as the register keeps them.
        $dashboard = $this->api('GET', '/api/v1/dashboard', token: $token)->assertOk()->json('data');
        $this->assertSame(['Di User', 'Stok IT', 'Service', 'Hilang'], array_column($dashboard['placements'], 'label'));
        $this->assertSame(['Layak', 'Perlu Service', 'Rusak', 'Belum dicek'], array_column($dashboard['conditions'], 'label'));
        $this->assertSame('Tipe radio kosong', collect($dashboard['issues'])->firstWhere('code', 'type_empty')['label']);
        $this->assertSame([['company' => '(kosong)', 'count' => 5]], $dashboard['by_company']);
        $this->assertSame([['type' => '(kosong)', 'count' => 5]], $dashboard['by_type']);
    }
}
