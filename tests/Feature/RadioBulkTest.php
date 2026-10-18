<?php

namespace Tests\Feature;

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\RadioManagement\Http\Controllers\RadioBulkController;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Services\RadioService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * Several radios at once from the register list (RadioBulk): verifying the radios ticked or every radio of a filter,
 * archiving and deleting radios ticked one by one, exporting the radios ticked; each with its permission on "Register
 * Radio", each radio as one radio would be (its history). The script of the bar: tests/tools/radio-bulk-behaviour.mjs.
 */
class RadioBulkTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;
    use UsesApi;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-18 10:00:00'));
    }

    private function radio(int $id, array $more = []): Radio
    {
        return Radio::create($more + ['dmr_id' => $id, 'serial_number' => sprintf('867TZB%04d', $id), 'user_name' => "Pengguna {$id}"]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin.radio@test.local')->first() ?? $this->makeUser('admin.radio@test.local', 'Admin Radio', 'Admin Radio Satu');
    }

    private function archived(int $id): Radio
    {
        $radio = $this->radio($id);
        app(RadioService::class)->archive($radio, 'Afkir', '2026-10-01', null, null);

        return $radio->fresh();
    }

    /** @return array<int, array{action: string, description: ?string, date: string}> */
    private function history(Radio $radio): array
    {
        return RadioHistory::query()->where('radio_id', $radio->id)->orderBy('id')->get()
            ->map(fn (RadioHistory $line) => ['action' => $line->action, 'description' => $line->description, 'date' => substr((string) $line->event_date, 0, 10)])
            ->all();
    }

    public function test_the_radios_ticked_are_verified_each_with_its_history(): void
    {
        $never = $this->radio(11);
        $later = $this->radio(12, ['last_verified_at' => '2026-10-17']);
        $archived = $this->archived(13);
        $this->actingAs($this->admin());

        $this->putJson(route('radio-management.radio.bulk-verify'), [
            'ids' => [$never->id, $later->id, $archived->id], 'event_date' => '2026-10-15', 'description' => 'Stock opname Weda',
        ])->assertOk()
            ->assertJsonPath('message', '2 radio diverifikasi. 1 dilewati.')->assertJsonPath('done', 2)
            ->assertJsonPath('skipped', [['radio' => '867TZB0013', 'reason' => 'sudah diarsipkan']]);

        // A "Verifikasi" line each, as from the page of the radio: the date of verification moves forward, never back.
        $this->assertSame([['action' => 'Verifikasi', 'description' => 'Stock opname Weda', 'date' => '2026-10-15']], $this->history($never));
        $this->assertSame('2026-10-15', $never->fresh()->last_verified_at->toDateString());
        $this->assertSame('2026-10-17', $later->fresh()->last_verified_at->toDateString());
        $this->assertCount(1, $this->history($later));
        $this->assertSame(['Arsip'], array_column($this->history($archived), 'action'));

        // Nothing typed: the words of a stock opname.
        $this->putJson(route('radio-management.radio.bulk-verify'), ['ids' => [$never->id], 'event_date' => '2026-10-18'])->assertOk();
        $this->assertSame(RadioBulkController::DEFAULT_NOTE, $this->history($never)[1]['description']);
        $this->assertSame('2026-10-18', $never->fresh()->last_verified_at->toDateString());

        // A day still to come, or nothing chosen: refused, and nothing is written.
        $this->putJson(route('radio-management.radio.bulk-verify'), ['ids' => [$never->id], 'event_date' => '2026-10-19'])->assertStatus(422);
        $this->putJson(route('radio-management.radio.bulk-verify'), ['ids' => [], 'event_date' => '2026-10-18'])->assertStatus(422)
            ->assertJsonPath('errors.0', 'Belum ada radio yang dipilih.');
        $this->assertCount(2, $this->history($never));
    }

    public function test_every_radio_of_the_filter_can_be_verified_up_to_the_limit(): void
    {
        $never = $this->radio(20);
        $recent = $this->radio(21, ['last_verified_at' => '2026-10-01']);
        $archivedNever = $this->archived(22);
        $this->actingAs($this->admin());

        // "Tgl verifikasi kosong", among the active radios (the filter of the list): only that one.
        $this->putJson(route('radio-management.radio.bulk-verify'), ['all' => 1, 'issue' => 'verify_empty', 'event_date' => '2026-10-18'])
            ->assertOk()->assertJsonPath('done', 1)->assertJsonPath('skipped', []);
        $this->assertSame('2026-10-18', $never->fresh()->last_verified_at->toDateString());
        $this->assertSame('2026-10-01', $recent->fresh()->last_verified_at->toDateString());
        $this->assertNull($archivedNever->fresh()->last_verified_at);

        // More than may be done at once: refused before anything is written (the ids are counted as they are sent).
        $this->putJson(route('radio-management.radio.bulk-verify'), ['ids' => range(1, 1001), 'event_date' => '2026-10-18'])
            ->assertStatus(422)->assertJsonPath('errors.0', 'Terlalu banyak radio (1001); paling banyak 1000 sekaligus. Persempit pilihan atau filternya.');
    }

    public function test_archiving_takes_one_reason_for_the_radios_ticked_one_by_one(): void
    {
        $a = $this->radio(31);
        $b = $this->radio(32);
        $this->actingAs($this->admin());

        $this->putJson(route('radio-management.radio.bulk-archive'), [
            'ids' => [$a->id, $b->id], 'archive_reason' => 'Afkir', 'archived_at' => '2026-10-18', 'note' => 'Rusak berat',
        ])->assertOk()->assertJsonPath('message', '2 radio diarsipkan.');
        $a->refresh();
        $this->assertTrue($a->isArchived());
        $this->assertSame([null, 31, 'Afkir'], [$a->dmr_id, $a->previous_dmr_id, $a->archive_reason]);
        $this->assertSame('Diarsipkan: Afkir. Radio ID 31 dilepas. Rusak berat', $this->history($a)[0]['description']);

        // Already archived: said for that radio.
        $c = $this->radio(33);
        $this->putJson(route('radio-management.radio.bulk-archive'), ['ids' => [$a->id, $c->id], 'archive_reason' => 'Afkir', 'archived_at' => '2026-10-18'])
            ->assertOk()->assertJsonPath('done', 1)->assertJsonPath('skipped.0.reason', 'Radio ini sudah diarsipkan');

        // Not every radio of a filter, not more than a hundred, not a reason of its own.
        $this->putJson(route('radio-management.radio.bulk-archive'), ['all' => 1, 'archive_reason' => 'Afkir', 'archived_at' => '2026-10-18'])
            ->assertStatus(422)->assertJsonPath('errors.0', 'Untuk aksi ini, centang radionya satu per satu (bukan semua hasil filter).');
        $this->putJson(route('radio-management.radio.bulk-archive'), ['ids' => range(1, 101), 'archive_reason' => 'Afkir', 'archived_at' => '2026-10-18'])
            ->assertStatus(422);
        $d = $this->radio(34);
        $this->putJson(route('radio-management.radio.bulk-archive'), ['ids' => [$d->id], 'archive_reason' => 'Dijual', 'archived_at' => '2026-10-18'])
            ->assertStatus(422);
        $this->assertFalse($d->fresh()->isArchived());
        $this->assertTrue($b->fresh()->isArchived());
    }

    public function test_deleting_needs_the_number_typed(): void
    {
        $a = $this->radio(41);
        $b = $this->radio(42);
        $this->actingAs($this->admin());

        $this->deleteJson(route('radio-management.radio.bulk-destroy'), ['ids' => [$a->id, $b->id], 'confirm' => '1'])->assertStatus(422)
            ->assertJsonPath('errors.0', 'Ketik jumlah radio yang akan dihapus (2) untuk melanjutkan.');
        $this->assertSame(2, Radio::query()->count());

        $this->deleteJson(route('radio-management.radio.bulk-destroy'), ['ids' => [$a->id, $b->id], 'confirm' => '2'])->assertOk()
            ->assertJsonPath('message', '2 radio dihapus.');
        // Soft deleted, the history kept.
        $this->assertSame(0, Radio::query()->count());
        $this->assertTrue(Radio::withTrashed()->find($a->id)->trashed());
        $this->assertSame(['Dihapus'], array_column($this->history($a), 'action'));

        $this->deleteJson(route('radio-management.radio.bulk-destroy'), ['all' => 1, 'confirm' => '1'])->assertStatus(422);
    }

    public function test_each_action_needs_its_permission_and_the_bar_offers_only_those(): void
    {
        $radio = $this->radio(51);
        $verify = fn () => $this->putJson(route('radio-management.radio.bulk-verify'), ['ids' => [$radio->id], 'event_date' => '2026-10-18']);
        $archive = fn () => $this->putJson(route('radio-management.radio.bulk-archive'), ['ids' => [$radio->id], 'archive_reason' => 'Afkir', 'archived_at' => '2026-10-18']);
        $delete = fn () => $this->deleteJson(route('radio-management.radio.bulk-destroy'), ['ids' => [$radio->id], 'confirm' => '1']);
        $page = fn () => (string) $this->get(route('radio-management.radio.index'))->assertOk()->getContent();

        // Read only: the radios ticked can be exported, nothing else.
        $this->actingAs($this->makeUser('baca@test.local', 'Viewer Radio', 'Viewer Satu'));
        $verify()->assertForbidden();
        $archive()->assertForbidden();
        $delete()->assertForbidden();
        $this->get(route('radio-management.radio.export', ['ids' => [$radio->id]]))->assertOk();
        $html = $page();
        $this->assertStringContainsString('id="radioBulkExport"', $html);
        $this->assertStringNotContainsString('id="radioBulkVerify"', $html);
        $this->assertStringNotContainsString('id="radioBulkDelete"', $html);

        // Update without Delete: verify and archive, not delete.
        $role = $this->roleWith(['radio-management.radio' => ['Read', 'Update']], 'Petugas Radio');
        $this->actingAs($this->makeUser('petugas@test.local', $role, 'Petugas Satu'));
        $html = $page();
        $this->assertStringContainsString('id="radioBulkVerify"', $html);
        $this->assertStringContainsString('id="radioBulkArchive"', $html);
        $this->assertStringNotContainsString('id="radioBulkDelete"', $html);
        $verify()->assertOk();
        $delete()->assertForbidden();
        $archive()->assertOk();

        // Admin Radio: everything.
        $this->actingAs($this->admin());
        $this->assertStringContainsString('id="radioBulkDelete"', $page());
    }

    public function test_the_radios_ticked_are_exported_and_listed_by_their_ids(): void
    {
        $a = $this->radio(61);
        $this->radio(62);
        $archived = $this->archived(63);
        $this->actingAs($this->admin());

        // Those radios only, whatever the other filters say (an archived one too: it was ticked).
        $response = $this->get(route('radio-management.radio.export', ['ids' => [$archived->id, $a->id], 'scope' => 'active']))->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(3, $sheet->getHighestDataRow());
        $serials = [$sheet->getCell('C2')->getValue(), $sheet->getCell('C3')->getValue()];
        sort($serials);
        $this->assertSame(['867TZB0061', '867TZB0063'], $serials);

        $this->getJson(route('radio-management.radio.list', ['ids' => [$a->id, 'x', -5]]))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $a->id);
    }

    public function test_the_script_of_the_bar(): void
    {
        $this->checkPageScript(route('radio-management.radio.index'), 'tests/tools/radio-bulk-behaviour.mjs', 10);
    }
}
