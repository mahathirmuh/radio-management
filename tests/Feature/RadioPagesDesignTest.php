<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioType;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * The radio pages in the design layer (docs/PLAN.md phase 2): Register Radio, the detail of a radio and Riwayat Radio.
 * A role sees only the buttons it may use; the server still checks every request.
 */
class RadioPagesDesignTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    private function radio(): Radio
    {
        return Radio::create(['dmr_id' => 101, 'serial_number' => '867TZB0101', 'user_name' => 'Pengguna Satu', 'radio_type_id' => RadioType::query()->value('id')]);
    }

    public function test_the_register_is_a_title_and_one_card_with_its_toolbar_filters_bar_and_table(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('radio-management.radio.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1 class="rm-title">Register Radio</h1>\s*<p class="rm-sub" id="radioSummary">#', $page);
        $this->assertSeeInOrder($page, [
            '<div class="rm-card">', 'class="rm-search"', 'id="radioSearch"', 'id="radioFilterButton"', 'id="radioResetButton"',
            'class="rm-seg sm ms-auto"', 'Export Excel', 'id="radioAddButton"',
            // The filters, the chips of those that are on, the bar of the radios ticked, the table and its foot, in the card.
            'class="rm-panel d-none" id="radioFilterCard"', 'id="radioApplied"', 'class="rm-bulk d-none" id="radioBulkBar"',
            '<table class="rm-table" id="radioTable">', 'class="pagination-container"',
        ]);
        // The foot speaks the language of the page.
        $this->assertStringContainsString('paginationLabels: { noun: radioText.noun }', $page);
        $this->assertStringContainsString('"noun":"radio"', $page);
        // No search button: the list follows what is typed.
        $this->assertStringNotContainsString('radioSearchButton', $page);
    }

    public function test_a_role_that_may_only_read_sees_no_button_to_change_radios(): void
    {
        $viewer = $this->makeUser('viewer@test.local', 'Viewer Radio');
        $page = (string) $this->actingAs($viewer)->get(route('radio-management.radio.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="radioAddButton"', $page);
        $this->assertStringContainsString('const radioMay = {"create":false,"update":false,"delete":false};', $page);
        $this->assertStringNotContainsString('id="radioBulkVerify"', $page);

        $admin = $this->makeUser('admin@test.local', 'Admin Radio');
        $page = (string) $this->actingAs($admin)->get(route('radio-management.radio.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="radioAddButton"', $page);
        $this->assertStringContainsString('const radioMay = {"create":true,"update":true,"delete":true};', $page);
    }

    public function test_the_detail_shows_the_radio_its_checks_facts_and_history_with_the_buttons_the_role_may_use(): void
    {
        $radio = $this->radio();

        $page = (string) $this->actingAs($this->superAdmin())->get(route('radio-management.radio.detail', $radio))->assertOk()->getContent();
        $this->assertSeeInOrder($page, [
            'class="rm-crumbs d-lg-none"', route('radio-management.radio.index'), 'Radio 101',
            'class="rm-hero"', 'Radio ID / MDC Code', '101', 'MDC 65', 'SN 867TZB0101',
            'id="radioDetailPrint"', 'Tambah riwayat', 'Arsipkan', 'class="rm-danger-icon"', 'Edit',
            'class="rm-info-grid"', 'Identitas unit', 'Pengguna', 'Program dan verifikasi',
            'class="rm-detail-lower"', 'Riwayat', 'Data asli Excel',
        ]);
        // The print pop-up of the list is on the detail too, and deleting asks for the Radio ID to be typed.
        $this->assertStringContainsString('id="radioPrintModal"', $page);
        $this->assertStringContainsString("const radioConfirmWord = '101';", $page);
        $this->assertStringNotContainsString('class="rm-banner"', $page);

        $page = (string) $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'))
            ->get(route('radio-management.radio.detail', $radio))->assertOk()->getContent();
        $this->assertStringContainsString('class="rm-banner"', $page);
        $this->assertStringContainsString('id="radioDetailPrint"', $page);
        foreach (['radioHistory.open()', 'radioArchive.open(', 'class="rm-danger-icon"', "radioForm.open('edit'"] as $button) {
            $this->assertStringNotContainsString($button, $page, $button);
        }
    }

    public function test_radio_history_is_a_row_of_filters_with_the_design_pickers_above_its_table(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('radio-management.history.index'))->assertOk()->getContent();

        $this->assertSeeInOrder($page, [
            '<h1 class="rm-title">Riwayat Radio</h1>', '<div class="rm-filter-row">', 'id="historySearch"', 'id="historyAction"',
            'type="hidden" id="historyFrom"', 'type="hidden" id="historyTo"', 'historyTable.reset()', '<table class="rm-table" id="historyTable">',
        ]);
        foreach (["rmPickers.multi('#historyAction'", "rmPickers.date('#historyFrom'", "rmPickers.date('#historyTo'"] as $picker) {
            $this->assertStringContainsString($picker, $page);
        }
        // The pickers are the layer's own: no select2 on this page any more.
        $this->assertStringNotContainsString('select2.min.js', $page);
    }

    /** @param array<int, string> $parts */
    private function assertSeeInOrder(string $page, array $parts): void
    {
        $at = 0;
        foreach ($parts as $part) {
            $found = strpos($page, $part, $at);
            $this->assertNotFalse($found, "\"{$part}\" (after the previous part)");
            $at = $found + strlen($part);
        }
    }
}
