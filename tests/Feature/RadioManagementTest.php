<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioIdService;
use Modules\RadioManagement\Services\RadioImportService;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

class RadioManagementTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return $this->superAdmin();
    }

    private function userWithRole(string $roleName): User
    {
        return $this->makeUser(str_replace(' ', '.', strtolower($roleName)) . '@test.local', $roleName, $roleName . ' Tester');
    }

    private function payload(array $overrides = []): array
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

    public function test_radio_id_rules_skip_hex_f_and_used_ids(): void
    {
        $this->assertTrue(RadioIdService::hasHexF(15));   // F
        $this->assertTrue(RadioIdService::hasHexF(255));  // FF
        $this->assertTrue(RadioIdService::hasHexF(751));  // 2EF
        $this->assertFalse(RadioIdService::hasHexF(16));  // 10
        $this->assertSame('3E9', RadioIdService::mdc(1001));

        Radio::create(['dmr_id' => 11, 'serial_number' => 'A', 'user_name' => 'x']);
        Radio::create(['dmr_id' => 12, 'serial_number' => 'B', 'user_name' => 'x', 'archived_at' => now()]);
        // No ID on record, but 13 is its last known ID: reserved, not handed out.
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 13, 'serial_number' => 'C', 'user_name' => 'x']);

        // 11 is used, 12 belongs to an archived radio (free), 13 is reserved, 15 contains F.
        $this->assertSame([12, 14, 16, 17], app(RadioIdService::class)->nextFree(4));

        $this->actingAs($this->admin());
        $response = $this->postJson(route('radio-management.radio.store'), $this->payload(['dmr_id' => 13]))
            ->assertStatus(422);
        $this->assertStringContainsString('dicadangkan', implode(' ', $response->json('errors')));
    }

    public function test_pages_render_for_super_admin(): void
    {
        $radio = Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB5309', 'user_name' => 'Iqbal']);
        $this->actingAs($this->admin());

        foreach ([
            route('dashboard.index'),
            route('radio-management.radio.index'),
            route('radio-management.radio.detail', $radio),
            route('radio-management.history.index'),
            route('radio-management.import.index'),
            route('radio-management.radio-type.index'),
            route('radio-management.company.index'),
            route('radio-management.department.index'),
            route('radio-management.channel-template.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->getJson(route('radio-management.radio.list'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.mdc', '14');
    }

    public function test_the_dashboard_has_no_button_to_the_register_in_its_header(): void
    {
        Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB5309', 'user_name' => 'Iqbal']);
        $this->actingAs($this->admin());
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();

        // The register is in the sidebar: no button for it next to the title.
        $this->assertStringContainsString('Dashboard Radio', $page);
        $this->assertDoesNotMatchRegularExpression('#<a[^>]*class="[^"]*\bbtn\b[^"]*"[^>]*>(?:(?!</a>).)*?Register Radio#s', $page);
        // The figures still lead to it.
        $this->assertStringContainsString(route('radio-management.radio.index') . '?scope=archived', $page);
    }

    public function test_create_validates_sn_and_radio_id(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('radio-management.radio.store'), $this->payload())
            ->assertOk();
        $radio = Radio::firstOrFail();
        $this->assertSame('867TZB9999', $radio->serial_number); // upper-cased
        $this->assertSame('Dibuat', $radio->histories()->first()->action);

        // Same SN again.
        $this->postJson(route('radio-management.radio.store'), $this->payload(['dmr_id' => 2002]))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Input tidak valid']);

        // Radio ID already used.
        $this->postJson(route('radio-management.radio.store'), $this->payload(['serial_number' => '867TZB0001']))
            ->assertStatus(422);

        // Radio ID 2031 = 0x7EF contains F.
        $response = $this->postJson(route('radio-management.radio.store'), $this->payload([
            'serial_number' => '867TZB0002',
            'dmr_id' => 2031,
        ]))->assertStatus(422);
        $this->assertStringContainsString('mengandung huruf F', implode(' ', $response->json('errors')));

        // "Di User" needs a user name.
        $this->postJson(route('radio-management.radio.store'), $this->payload([
            'serial_number' => '867TZB0003',
            'dmr_id' => 2004,
            'user_name' => '',
        ]))->assertStatus(422);
    }

    public function test_update_archive_restore_keep_history(): void
    {
        $this->actingAs($this->admin());
        $company = RadioCompany::where('code', 'MTI')->firstOrFail();
        $this->postJson(route('radio-management.radio.store'), $this->payload())->assertOk();
        $radio = Radio::firstOrFail();

        // Handover: new user + company.
        $this->putJson(route('radio-management.radio.update', $radio), $this->payload([
            'user_name' => 'Andi Wijaya',
            'company_id' => $company->id,
        ]))->assertOk();
        $handover = $radio->histories()->first();
        $this->assertSame('Serah Terima', $handover->action);
        $this->assertSame('Budi Santoso', $handover->changes['user_name']['old']);
        $this->assertSame('Andi Wijaya', $handover->changes['user_name']['new']);

        // Archive releases the ID.
        $this->putJson(route('radio-management.radio.archive', $radio), [
            'archive_reason' => 'Pindah Site',
            'archived_at' => now()->toDateString(),
            'note' => 'Dikirim ke Konawe',
        ])->assertOk();
        $radio->refresh();
        $this->assertNull($radio->dmr_id);
        $this->assertSame(2001, $radio->previous_dmr_id);
        $this->assertTrue($radio->isArchived());

        // Restore gets the old ID back while it is still free.
        $this->putJson(route('radio-management.radio.restore', $radio))->assertOk()->assertJsonPath('dmr_id', 2001);

        // A manual "Program" entry moves the programming date forward.
        $this->putJson(route('radio-management.radio.add-history', $radio), [
            'action' => 'Program',
            'event_date' => now()->toDateString(),
            'description' => 'Program ulang template General',
        ])->assertOk();
        $this->assertSame(now()->toDateString(), $radio->fresh()->last_programmed_at->toDateString());

        $this->getJson(route('radio-management.history.list', ['search' => '867TZB9999']))
            ->assertOk()
            ->assertJsonPath('meta.total', 5); // Dibuat, Serah Terima, Arsip, Dipulihkan, Program
    }

    public function test_issue_filter_finds_duplicate_ids_and_reprogram_needs(): void
    {
        $template = ChannelTemplate::create(['name' => 'General v2', 'effective_date' => now()->subDays(10)]);
        Radio::create(['dmr_id' => 601, 'serial_number' => '867TVP1379', 'user_name' => 'Arief']);
        Radio::create(['dmr_id' => 601, 'serial_number' => '866TTYR1857', 'user_name' => 'BMS']);
        Radio::create([
            'dmr_id' => 602, 'serial_number' => '867TVP1380', 'user_name' => 'Rina',
            'channel_template_id' => $template->id, 'last_programmed_at' => now()->subMonth(),
        ]);
        $this->actingAs($this->admin());

        $this->getJson(route('radio-management.radio.list', ['issue' => 'id_duplicate']))
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(route('radio-management.radio.list', ['issue' => 'sn_format']))
            ->assertOk()->assertJsonPath('meta.total', 1); // 866TTYR1857 has 11 characters
        $this->getJson(route('radio-management.radio.list', ['issue' => 'reprogram']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.serial_number', '867TVP1380');
        // Searching by MDC code (602 = 0x25A).
        $this->getJson(route('radio-management.radio.list', ['search' => '25A']))
            ->assertOk()->assertJsonPath('data.0.dmr_id', 602);
    }

    public function test_viewer_role_is_read_only(): void
    {
        $this->actingAs($this->userWithRole('Viewer Radio'));

        $this->get(route('radio-management.radio.index'))->assertOk();
        $this->getJson(route('radio-management.radio.list'))->assertOk();
        $this->postJson(route('radio-management.radio.store'), $this->payload())->assertForbidden();
        $this->postJson(route('radio-management.company.store'), ['code' => 'XYZ'])->assertForbidden();
    }

    public function test_master_data_in_use_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $this->postJson(route('radio-management.company.store'), ['code' => 'abc', 'name' => 'PT ABC', 'is_active' => 1])
            ->assertOk();
        $company = RadioCompany::where('code', 'ABC')->firstOrFail(); // code is upper-cased
        Radio::create(['serial_number' => '867TZB1111', 'user_name' => 'x', 'company_id' => $company->id]);

        $this->deleteJson(route('radio-management.company.destroy', $company->id))->assertStatus(422);
        $this->postJson(route('radio-management.company.store'), ['code' => 'ABC'])->assertStatus(422); // duplicate
    }

    public function test_owner_says_mti_or_non_mti_by_the_company_that_owns_the_radio(): void
    {
        $company = fn (string $code) => RadioCompany::firstOrCreate(['code' => $code])->id;
        Radio::create(['dmr_id' => 20, 'serial_number' => 'A', 'user_name' => 'milik MTI', 'owner_company_id' => $company('MTI'), 'company_id' => $company('MMS')]);
        Radio::create(['dmr_id' => 21, 'serial_number' => 'B', 'user_name' => 'milik MMS', 'owner_company_id' => $company('MMS'), 'company_id' => $company('MTI')]);
        Radio::create(['dmr_id' => 22, 'serial_number' => 'C', 'user_name' => 'milik SJS', 'owner_company_id' => $company('SJS')]);
        // No owner filled in: neither MTI nor Non MTI, whoever uses it.
        Radio::create(['dmr_id' => 23, 'serial_number' => 'D', 'user_name' => 'tanpa pemilik', 'company_id' => $company('MTI')]);
        $this->actingAs($this->admin());
        $list = fn (array $query = []) => $this->getJson(route('radio-management.radio.list', $query))->assertOk();

        // It follows from the owner of the radio (not from who uses it), and is nothing that is typed in.
        $this->assertSame(
            [20 => 'MTI', 21 => 'Non MTI', 22 => 'Non MTI', 23 => null],
            collect($list()->json('data'))->pluck('owner_group', 'dmr_id')->all()
        );
        $this->assertSame([20], collect($list(['owner_group' => 'mti'])->json('data'))->pluck('dmr_id')->all());
        $this->assertSame([21, 22], collect($list(['owner_group' => 'non_mti'])->json('data'))->pluck('dmr_id')->all());
        // Anything else is no filter; with another filter both count.
        $this->assertCount(4, $list(['owner_group' => 'x'])->json('data'));
        $this->assertSame([21], collect($list(['owner_group' => 'non_mti', 'owner' => [$company('MMS')]])->json('data'))->pluck('dmr_id')->all());
        $this->assertSame([], $list(['owner_group' => 'mti', 'owner' => [$company('MMS')]])->json('data'));

        // The page has the column, after Company, and the filter; the detail says it too.
        $page = (string) $this->get(route('radio-management.radio.index'))->assertOk()->getContent();
        // (The header is drawn by the page's script: one title per column of the scope, Owner after Company.)
        $this->assertMatchesRegularExpression("#\['Company'\], \['Owner'\], \['Template'\]#", $page);
        $this->assertMatchesRegularExpression('#<select id="filterOwnerGroup"[^>]*>\s*<option value="">Semua</option>\s*<option value="mti">MTI</option>\s*<option value="non_mti">Non MTI</option>#', $page);
        $this->assertStringContainsString("['pick', 'index', 'dmr_id', 'serial_number', 'user_name', 'company', 'owner_group', 'template', 'placement', 'condition', 'last_verified_at', 'issues', 'actions']", $page, 'thirteen columns, the box and the number first');
        $this->get(route('radio-management.radio.detail', Radio::where('dmr_id', 21)->value('id')))->assertOk()->assertSeeInOrder(['Pemilik Aset', 'Owner', 'Non MTI']);

        // The code that is MTI is a setting, and it is compared without regard to case and blanks.
        RadioCompany::where('code', 'MMS')->update(['code' => ' mms ']);
        config(['radio.owner_mti_code' => 'MMS']);
        $this->assertSame(
            [20 => 'Non MTI', 21 => 'MTI', 22 => 'Non MTI', 23 => null],
            collect($list()->json('data'))->pluck('owner_group', 'dmr_id')->all()
        );
        $this->assertSame([21], collect($list(['owner_group' => 'mti'])->json('data'))->pluck('dmr_id')->all());
    }

    public function test_export_carries_the_owner_beside_the_company_that_owns_the_radio(): void
    {
        $mti = RadioCompany::firstOrCreate(['code' => 'MTI'])->id;
        $mms = RadioCompany::firstOrCreate(['code' => 'MMS'])->id;
        Radio::create(['dmr_id' => 20, 'serial_number' => 'A', 'user_name' => 'x', 'owner_company_id' => $mti]);
        Radio::create(['dmr_id' => 21, 'serial_number' => 'B', 'user_name' => 'y', 'owner_company_id' => $mms]);
        Radio::create(['dmr_id' => 22, 'serial_number' => 'C', 'user_name' => 'z']);

        $export = app(\Modules\RadioManagement\Exports\RadioExport::class, ['radios' => Radio::with('ownerCompany')->orderBy('dmr_id')->get()]);
        $headings = $export->headings();
        $at = array_search('Owner', $headings, true);
        $this->assertSame(['Pemilik Aset', 'Owner', 'Nama User / Unit'], array_slice($headings, $at - 1, 3));
        $rows = Radio::with(['ownerCompany', 'radioType', 'company', 'department', 'channelTemplate'])->orderBy('dmr_id')->get()->map(fn (Radio $radio) => $export->map($radio));
        $this->assertSame([['MTI', 'MTI'], ['MMS', 'Non MTI'], [null, null]], $rows->map(fn (array $row) => [$row[$at - 1], $row[$at]])->all());
        $this->assertCount(count($headings), $rows[0]);
    }

    public function test_export_downloads_excel(): void
    {
        Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB5309', 'user_name' => 'Iqbal']);
        $this->actingAs($this->admin());

        $response = $this->get(route('radio-management.radio.export'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_import_upload_through_the_web_page(): void
    {
        $file = base_path('Radio Register 07-08-25.xlsx');
        if (! is_file($file)) {
            $this->markTestSkipped('Legacy register file not present.');
        }
        $this->actingAs($this->admin());
        \Illuminate\Support\Facades\Storage::fake('local'); // keep the uploaded copy out of storage/app

        $upload = new \Illuminate\Http\UploadedFile($file, 'Radio Register 07-08-25.xlsx', null, null, true);
        $this->post(route('radio-management.import.store'), ['file' => $upload, 'fresh' => 1, 'konawe' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('report.imported', 1016);

        $this->get(route('radio-management.import.index'))->assertOk()->assertSee('Radio Register 07-08-25.xlsx');
    }

    public function test_import_of_the_legacy_register(): void
    {
        $file = base_path('Radio Register 07-08-25.xlsx');
        if (! is_file($file)) {
            $this->markTestSkipped('Legacy register file not present.');
        }

        $report = app(RadioImportService::class)->import($file);

        $this->assertSame(1016, $report['imported']);
        $this->assertSame(5, $report['imported_archived']); // handed over to Konawe
        $this->assertGreaterThan(0, $report['flagged']);

        // ID 601 is used twice in the Excel: both radios are imported and flagged.
        $this->assertSame(2, Radio::where('dmr_id', 601)->where('needs_review', true)->count());
        // M3690 is an Excel autofill mistake for M3688.
        $this->assertFalse(RadioType::where('name', 'MotoTrbo XiR M3690')->exists());
        // "Available" in the Sep 2024 audit means found and fine.
        $this->assertSame(Condition::Layak, Radio::where('serial_number', '867TVX4057')->value('condition'));
        // Original Excel values are kept.
        $this->assertSame(20, Radio::where('serial_number', '8867TYB4014')->value('source_row'));
        // Row 267 has a blank Radio ID cell in slot 276: kept as last known ID and flagged, not assigned.
        $blank = Radio::where('serial_number', '867TYRG698')->firstOrFail();
        $this->assertNull($blank->dmr_id);
        $this->assertSame(276, $blank->previous_dmr_id);
        $this->assertTrue($blank->needs_review);
        $this->assertNotContains(276, app(RadioIdService::class)->nextFree(10));

        // A second import without "fresh" is refused.
        $this->expectException(\RuntimeException::class);
        app(RadioImportService::class)->import($file);
    }
}
