<?php

namespace Tests\Feature;

use App\Models\Log\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioIdService;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * Perlu Perhatian and Riwayat Radio in English: the pages, their scripts' words and what the server says back, while
 * what is kept (the kind of a history line, what a fix writes into the history and the audit trail) stays Indonesian.
 */
class AttentionHistoryLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;
    use UsesEnglish;

    protected bool $seed = true;

    private int $next = 0;

    /** A radio without any problem, unless $other gives it one. */
    private function radio(array $other = []): Radio
    {
        $this->next++;
        $type = RadioType::query()->firstOrCreate(['name' => 'XiR P8668i'], ['category' => 'HT', 'is_active' => true]);
        $id = RadioIdService::nextWithoutHexF(4096 + $this->next);

        return Radio::create($other + [
            'dmr_id' => $id,
            'serial_number' => sprintf('867TZB%04d', $id),
            'radio_type_id' => $type->id,
            'user_name' => "Pemakai {$this->next}",
            'placement' => 'di_user',
            'condition' => 'layak',
            'last_verified_at' => now()->subMonth()->toDateString(),
        ]);
    }

    public function test_needs_attention_is_in_english(): void
    {
        $this->radio(['last_verified_at' => null]);
        $this->actingAs($this->superAdmin());
        $this->useEnglish();

        $page = (string) $this->get(route('radio-management.attention.index'))->assertOk()->getContent();
        foreach ([
            '<h1 class="rm-title">Needs Attention</h1>',

            'Run the checks now</button>',
            '<select id="attnWho" class="form-select" aria-label="Assigned to">',
            '<option value="mine">Assigned to me</option>',
            '<option value="none">Unassigned</option>',
            'id="attnMore">Show more</button>',
            'Choose an item in the middle to see its data.',
        ] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        // The checks by their English names, and the words of the script.
        $this->assertStringContainsString('Duplicate SN', $page);
        $this->assertStringContainsString('Verification date empty', $page);
        $this->assertStringContainsString('"dismiss_title":"Dismiss this item?"', $page);
        $this->assertStringContainsString('"items":"{1} :count item|[2,*] :count items"', $page);
        foreach (['Perlu Perhatian</h1>', 'Jalankan cek sekarang', 'Tugas saya', 'Belum ditugaskan', 'Tampilkan lagi', 'SN dobel', 'Abaikan item ini?', 'Terapkan'] as $text) {
            $this->assertStringNotContainsString($text, $page);
        }
    }

    public function test_the_items_their_fixes_and_the_answers_are_in_english_what_is_kept_stays_indonesian(): void
    {
        $first = $this->radio(['dmr_id' => 2001, 'serial_number' => '867TZB5309', 'user_name' => 'Pemakai Satu']);
        $twin = $this->radio(['dmr_id' => 2002, 'serial_number' => '867TZB5309', 'user_name' => 'Pemakai Dua']);
        $noSn = $this->radio(['dmr_id' => 2003, 'serial_number' => null]);
        $stale = $this->radio(['last_verified_at' => now()->subYears(2)->toDateString()]);
        $this->actingAs($this->superAdmin());
        $this->useEnglish();

        // The list and the item at the right.
        $list = $this->getJson(route('radio-management.attention.list', ['check' => 'sn_duplicate']))->assertOk()->json('data');
        $this->assertSame(['Radios 2001 and 2002', 'Pemakai Satu and Pemakai Dua'], [$list[0]['side'], $list[0]['who']]);
        $item = $this->getJson(route('radio-management.attention.show', ['check' => 'sn_duplicate', 'subject' => '867TZB5309']))->assertOk()->json('data');
        $this->assertSame(['SN 867TZB5309 is used by 2 radios', 'Duplicate SN'], [$item['title'], $item['label']]);
        $this->assertSame(['Radio ID', 'SN', 'User/unit', 'Radio type', 'Location', 'Last verified', 'Last programmed', 'Document No.'],
            array_column($item['records'][0]['rows'], 'label'));
        $this->assertSame(['Created ' . $first->created_at->format('d-m-Y'), 'With user · Good'], [$item['records'][0]['tag'], $item['records'][0]['rows'][4]['value']]);
        $this->assertSame(['Correct the SN of radio 2002', 'Archive radio 2002 as a duplicate', 'These radios really are different'],
            array_column($item['options'], 'title'));
        $this->assertSame('Its history stays and Radio ID 2002 is released.', $item['options'][1]['detail']);

        // A radio without an SN is "no SN"; a check's hint is in English too.
        $empty = $this->getJson(route('radio-management.attention.show', ['check' => 'sn_empty', 'subject' => (string) $noSn->id]))->assertOk()->json('data');
        $this->assertSame(['ID 2003 · no SN', 'Radio ID 2003 · no SN has no SN yet', 'Fill in the SN'], [$empty['head'], $empty['title'], $empty['options'][0]['title']]);
        $old = $this->getJson(route('radio-management.attention.show', ['check' => 'verify_stale', 'subject' => (string) $stale->id]))->assertOk()->json('data');
        $this->assertSame(['Last ' . $stale->last_verified_at->format('d-m-Y'), 'Last ' . $stale->last_verified_at->format('d-m-Y')], [$old['side'], $old['note']]);
        $this->assertSame('Radio ' . $stale->display_name . ' was last verified over 12 months ago', $old['title']);

        // What the server says back.
        $fix = fn (string $check, string $subject, string $option, ?string $value = null) => $this->putJson(route('radio-management.attention.fix'),
            array_filter(['check' => $check, 'subject' => $subject, 'option' => $option, 'value' => $value], fn ($v) => $v !== null));
        $fix('sn_duplicate', '867TZB5309', "sn:{$twin->id}", '')->assertStatus(422)->assertJsonPath('errors.0', 'Type the right SN.');
        $fix('sn_duplicate', '867TZB5309', "sn:{$twin->id}", '867TZB5311')->assertOk()->assertJsonPath('message', 'The SN of radio ID 2002 · 867TZB5309 is now 867TZB5311.');
        $fix('sn_duplicate', '867TZB5309', "sn:{$twin->id}", '867TZB5312')->assertStatus(422)
            ->assertJsonPath('errors.0', 'This item is gone: its data has been fixed or has changed. Reload.');
        $this->getJson(route('radio-management.attention.show', ['check' => 'sn_duplicate', 'subject' => '867TZB5309']))->assertNotFound()
            ->assertJsonPath('message', 'This item is gone: its data has been fixed or has changed.');
        $fix('verify_stale', (string) $stale->id, "verify:{$stale->id}")->assertOk()
            ->assertJsonPath('message', 'Verification today recorded for radio ' . $stale->display_name . '.');
        $this->putJson(route('radio-management.attention.dismiss'), ['check' => 'sn_empty', 'subject' => (string) $noSn->id, 'reason' => ' '])
            ->assertStatus(422)->assertJsonPath('message', 'Invalid input')->assertJsonPath('errors.0', 'Write why this item is dismissed.');
        $this->putJson(route('radio-management.attention.assign'), ['check' => 'sn_empty', 'subject' => (string) $noSn->id, 'user_id' => ''])
            ->assertOk()->assertJsonPath('message', 'The item is no longer assigned to anyone.');
        $this->putJson(route('radio-management.attention.dismiss'), ['check' => 'sn_empty', 'subject' => (string) $noSn->id, 'reason' => 'Spare radio'])
            ->assertOk()->assertJsonPath('message', 'Item dismissed. It comes back when the data of its radios changes.');

        // What is kept stays Indonesian.
        $line = RadioHistory::query()->where('radio_id', $stale->id)->latest('id')->first();
        $this->assertSame(['Verifikasi', 'Diverifikasi fisik (Perlu Perhatian).'], [$line->action, $line->description]);
        $audit = AuditLog::query()->where('action', 'attention.dismissed')->sole();
        $this->assertSame(['SN kosong: ID 2003 · tanpa SN', ['Alasan' => 'Spare radio']], [$audit->entity_label, $audit->new_values]);
    }

    public function test_the_script_of_needs_attention_speaks_english(): void
    {
        $this->useEnglish();
        $this->checkPageScript(route('radio-management.attention.index'), 'tests/tools/attention-inbox-english.mjs', 6);
    }

    public function test_radio_history_is_in_english_with_the_kinds_kept_in_indonesian(): void
    {
        $radio = $this->radio();
        RadioHistory::create(['radio_id' => $radio->id, 'event_date' => now()->toDateString(), 'action' => 'Verifikasi', 'description' => 'Dicek fisik di gudang']);
        $this->actingAs($this->superAdmin());
        $this->useEnglish();

        $page = (string) $this->get(route('radio-management.history.index'))->assertOk()->getContent();
        foreach ([
            '<h1 class="rm-title">Radio History</h1>',

            'placeholder="Radio ID, SN, user, description, document no."',
            '<label for="historyFrom" class="form-label">From date</label>',
            '<th>Date</th>',
            '<th>Description</th>',
            '<th class="text-end">By</th>',
            '"all_kinds":"All kinds"',
        ] as $text) {
            $this->assertStringContainsString($text, $page);
        }
        // The kinds: the one kept is sent, its English name shown, in the order of the names.
        $kinds = [
            '<option value="Arsip">Archived</option>', '<option value="Dibuat">Created</option>', '<option value="Serah Terima">Handover</option>',
            '<option value="Program">Programming</option>', '<option value="Verifikasi">Verification</option>',
        ];
        $at = 0;
        foreach ($kinds as $kind) {
            $found = strpos($page, $kind, $at);
            $this->assertNotFalse($found, $kind);
            $at = $found + strlen($kind);
        }
        foreach (['Riwayat Radio</h1>', 'Dari tanggal', 'Semua jenis', '<th>Keterangan</th>', 'Belum ada riwayat', 'kejadian'] as $text) {
            $this->assertStringNotContainsString($text, $page);
        }

        // A line nobody wrote is the system's; its kind and text are as kept.
        $line = $this->getJson(route('radio-management.history.list', ['action' => ['Verifikasi']]))->assertOk()->json('data.0');
        $this->assertSame(['Verifikasi', 'Dicek fisik di gudang', 'System'], [$line['action'], $line['description'], $line['user']]);
    }

    public function test_in_indonesian_nothing_changes(): void
    {
        $this->radio(['serial_number' => null, 'dmr_id' => 2004]);
        $this->actingAs($this->superAdmin());

        $item = $this->getJson(route('radio-management.attention.list', ['check' => 'sn_empty']))->assertOk()->json('data.0');
        $this->assertSame('ID 2004 · tanpa SN', $item['head']);
        $page = (string) $this->get(route('radio-management.history.index'))->assertOk()->getContent();
        // The kinds in the order they always had.
        $this->assertMatchesRegularExpression('#<option value="Arsip">Arsip</option>\s*<option value="Catatan">Catatan</option>\s*<option value="Dibuat">Dibuat</option>#', $page);
    }
}
