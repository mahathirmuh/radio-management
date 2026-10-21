<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioType;
use Modules\SharePointDocs\Models\EditRequest;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The frame of every page (the design refresh, docs/PLAN.md phase 1): the design layer radio-ui.css, the sidebar with its
 * sections, groups, icon rail and the number of waiting requests, the top bar with the user menu, and the footer.
 */
class ShellTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    public function test_every_page_loads_the_design_layer_after_custom_css_and_inter_only(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#assets/css/custom\.css.*assets/css/radio-ui\.css\?v=\d+#s', $page);
        $this->assertStringNotContainsString('fonts.googleapis.com', $page);
        $this->assertStringNotContainsString('Public+Sans', $page);
        // The rail is put on before anything is drawn: in the head.
        $this->assertStringContainsString("classList.add('rm-rail')", (string) strstr($page, '</head>', true));
    }

    public function test_the_sign_in_page_loads_inter_the_icons_and_the_design_layer(): void
    {
        $page = (string) $this->get(route('login'))->assertOk()->getContent();

        foreach (['assets/css/fonts/fonts.css', 'assets/css/radio-ui.css', 'icons-webfont@2.36.0/tabler-icons.min.css'] as $sheet) {
            $this->assertStringContainsString($sheet, $page, $sheet);
        }
        // It linked a stylesheet that does not exist, so the page had the system font.
        $this->assertStringNotContainsString('googleApis-publicSans.css', $page);
    }

    public function test_the_sign_in_page_shows_merdeka_tsingshan_indonesia_between_the_group_logos(): void
    {
        $page = (string) $this->get(route('login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="rm-login-logos">\s*<img src="[^"]*/branding/mcg-logo\.png"[^>]*>\s*<img src="[^"]*/branding/mti-corporate\.svg" class="rm-login-logo-mti"[^>]*alt="Merdeka Tsingshan Indonesia">\s*<img src="[^"]*/branding/mbm-logo\.jpg"#', $page);
        $this->assertFileExists(public_path('assets/img/branding/mti-corporate.svg'));
    }

    public function test_the_sidebar_shows_its_sections_marks_the_page_and_opens_its_group(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('radio-management.radio-type.index'))->assertOk()->getContent();

        foreach (['Radio', 'Dokumen', 'Settings'] as $section) {
            $this->assertStringContainsString('<li class="rm-nav-section" role="presentation"><span>' . $section . '</span></li>', $page, $section);
        }
        // The page's entry is marked, in its group, which is open; in the rail the group's icon leads to its first page.
        $this->assertMatchesRegularExpression('#class="rm-nav-link rm-nav-kid is-active" href="' . preg_quote(route('radio-management.radio-type.index'), '#') . '"\s+aria-current="page"#', $page);
        $this->assertMatchesRegularExpression('#<li class="rm-nav-item rm-nav-group is-open has-active" id="rm-navgroup-\d+">\s*<button type="button" class="rm-nav-link" data-rm-group="\d+"\s+data-rm-first="' . preg_quote(route('radio-management.radio-type.index'), '#') . '" data-rm-tip="Master Data Radio"\s+aria-expanded="true"#', $page);
        // The other group is closed, and nothing else is the current page.
        $this->assertMatchesRegularExpression('#<li class="rm-nav-item rm-nav-group " id="rm-navgroup-\d+">\s*<button[^>]*data-rm-tip="User Management"\s+aria-expanded="false"#', $page);
        $sidebar = (string) strstr((string) strstr($page, '<aside'), '</aside>', true);
        $this->assertSame(1, substr_count($sidebar, 'aria-current="page"'));
        // Each entry has its name for the rail, and an icon.
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('radio-management.history.index'), '#') . '" data-rm-tip="Riwayat Radio"\s*>\s*<span class="rm-nav-icon"><i class="ti ti-history"></i></span>#', $page);
    }

    public function test_every_page_of_a_menu_marks_its_entry(): void
    {
        $radio = Radio::create(['dmr_id' => 101, 'serial_number' => '867TZB0101', 'user_name' => 'Pengguna Satu', 'radio_type_id' => RadioType::query()->value('id')]);

        $page = (string) $this->actingAs($this->superAdmin())->get(route('radio-management.radio.detail', $radio))->assertOk()->getContent();

        // The detail of a radio belongs to "Register Radio" (its route is of the same menu key).
        $this->assertMatchesRegularExpression('#class="rm-nav-link is-active" href="' . preg_quote(route('radio-management.radio.index'), '#') . '" data-rm-tip="Register Radio"\s+aria-current="page"#', $page);
    }

    public function test_the_entry_of_edit_approvals_shows_how_many_requests_wait(): void
    {
        $admin = $this->superAdmin();
        $page = (string) $this->actingAs($admin)->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('rm-nav-count', $page, 'nothing waits: no number');

        foreach ([EditRequest::PENDING, EditRequest::PENDING, EditRequest::APPLIED, EditRequest::REJECTED] as $i => $status) {
            DB::table('sharepoint_edit_requests')->insert([
                'workbook' => 'ict-masterlist', 'sheet' => 'COF', 'cell' => 'E' . ($i + 2), 'line' => $i + 2, 'column_title' => 'Nama',
                'old_text' => 'Lama', 'new_kind' => EditRequest::KIND_TEXT, 'new_value' => 'Baru', 'status' => $status,
                'requested_by' => $admin->id, 'requested_by_name' => $admin->name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('documents.edit-approvals.index'), '#') . '"[^>]*>.*?<span class="rm-badge blue rm-nav-count" title="2 pengajuan menunggu">2</span>\s*<span class="rm-nav-dot blue" aria-hidden="true"></span>#s', $page);
        $this->assertSame(1, substr_count($page, 'rm-nav-count'));

        // Somebody without the entry gets no number.
        $page = (string) $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'))->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('documents.edit-approvals.index'), $page);
        $this->assertStringNotContainsString('rm-nav-count', $page);
    }

    public function test_the_top_bar_has_the_switch_the_title_the_company_the_bell_and_the_user_menu(): void
    {
        $admin = $this->superAdmin();
        $page = (string) $this->actingAs($admin)->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<button id="sidebarControl" type="button" class="rm-icon-btn is-ringed" aria-controls="sidebar-menu"\s+aria-pressed="false"#', $page);
        $this->assertMatchesRegularExpression('#<div class="rm-topbar-title">\s*Dashboard\s*</div>#', $page);
        $this->assertStringContainsString('<span class="rm-topbar-company">' . e($admin->company->name) . '</span>', $page);
        $this->assertStringContainsString('<div class="dropdown" id="notifBell">', $page);
        $this->assertStringContainsString('<span class="rm-user-role">Super Admin</span>', $page);
        $this->assertStringContainsString('<div class="rm-menu-head-mail">' . e($admin->email) . '</div>', $page);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(route('notifications.index'), '#') . '" class="dropdown-item rm-menu-item">\s*<i class="ti ti-bell" aria-hidden="true"></i> Notifikasi#', $page);
        $this->assertMatchesRegularExpression('#class="dropdown-item rm-menu-item is-danger"[^>]*>\s*<i class="ti ti-logout" aria-hidden="true"></i> Keluar#', $page);
        $this->assertStringContainsString('<form id="logout-form" action="' . route('logout') . '" method="POST"', $page);
        // No profile page yet, so no entry for one.
        $this->assertStringNotContainsString('My profile', $page);
        // Below 992px the top bar is hidden: the sidebar keeps a way out.
        $this->assertStringContainsString('<span class="rm-nav-label">Keluar (' . e($admin->name) . ')</span>', $page);
    }

    public function test_the_footer_names_merdeka_group_and_the_version(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();

        // The version opens what changed in each version (ChangelogDialogTest).
        $this->assertMatchesRegularExpression('#<footer class="footer d-print-none rm-footer">\s*<span>Copyright &copy; ' . date('Y') . ', Development by <a[^>]*>Merdeka Group</a></span>\s*<button type="button" class="rm-version" id="rmVersion"[^>]*>v' . preg_quote((string) config('app.version'), '#') . '</button>#', $page);
    }

    public function test_the_sidebar_script_switches_the_rail_folds_groups_and_names_icons(): void
    {
        $this->checkPageScript(route('radio-management.radio-type.index'), 'tests/tools/sidebar-behaviour.mjs', 10);
    }

    public function test_the_shared_helpers_draw_the_foot_of_a_table_toasts_dialogs_and_dates(): void
    {
        $this->checkPageScript(route('dashboard.index'), 'tests/tools/design-helpers-behaviour.mjs', 10);
    }
}
