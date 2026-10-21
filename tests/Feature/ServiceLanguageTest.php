<?php

namespace Tests\Feature;

use App\Models\Log\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Exports\ServiceTicketExport;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Models\ServiceTicket;
use Modules\RadioManagement\Models\ServiceTicketEvent;
use Modules\RadioManagement\Services\ServiceTickets;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * "Servis & Perbaikan" in English (ServiceTicketTest checks it in Indonesian): the page, what the server answers, and the
 * activity of a ticket in the words of the page. What is written down (the activity, the radio's history, the audit
 * trail) and the Excel export stay in Indonesian.
 */
class ServiceLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;
    use UsesEnglish;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useEnglish();
    }

    private function radio(array $other = []): Radio
    {
        $type = RadioType::query()->firstOrCreate(['name' => 'XiR P8668i'], ['category' => 'HT']);
        $department = RadioDepartment::query()->firstOrCreate(['name' => 'Laboratory']);

        return Radio::create($other + [
            'dmr_id' => 751,
            'serial_number' => '903KXM2011',
            'radio_type_id' => $type->id,
            'user_name' => 'Pemakai Contoh',
            'department_id' => $department->id,
            'placement' => 'di_user',
            'condition' => 'perlu_service',
        ]);
    }

    public function test_the_page_is_in_english(): void
    {
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.service.index'))->assertOk()->getContent();

        foreach (['<h1 class="rm-title">Service &amp; Repair</h1>', 'Open tickets', 'Cost this quarter',
            'All service centers', 'Clear filters', 'New ticket', 'Reported by', 'Duration', 'Add a note', 'New service ticket',
            'Create ticket', 'Radio location afterwards', 'becomes <b>In service</b>', 'Save note', 'Sent for service', 'Mark as in repair'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        foreach (['Servis &amp; Perbaikan', 'Semua pusat servis', 'Tiket baru', 'Tambah catatan', 'Tiket servis baru', 'Buat tiket',
            'Dikirim ke servis', 'Belum dikirim', 'Lokasi radio sesudahnya', 'Simpan catatan', 'Rata-rata lama servis', 'Tandai'] as $part) {
            $this->assertStringNotContainsString($part, $page, $part);
        }
    }

    public function test_the_answers_are_in_english_and_what_is_written_down_stays_in_indonesian(): void
    {
        $this->withoutSessionTimeouts();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $radio = $this->radio();
        $this->actingAs($this->superAdmin());
        $store = fn (array $data) => $this->postJson(route('radio-management.service.store'), $data);

        $this->assertSame('With user', $this->getJson(route('radio-management.service.radios', ['q' => '751']))->json('data.0.placement'));
        $store(['radio_id' => $radio->id, 'issue' => 'LCD retak'])->assertCreated()->assertJsonPath('message', 'Ticket SRV-0001 created.');
        $ticket = ServiceTicket::query()->sole();
        $store(['radio_id' => $radio->id, 'issue' => 'LCD retak'])->assertStatus(422)->assertJsonPath('message', 'Invalid input')
            ->assertJsonPath('errors.0', 'This radio still has an open service ticket: SRV-0001.');
        $store(['issue' => 'LCD retak'])->assertStatus(422)->assertJsonPath('errors.0', 'Choose the radio that needs service.');

        $move = fn (string $status, array $other = []) => $this->putJson(route('radio-management.service.status', $ticket->id), ['status' => $status] + $other);
        $note = fn (string $text) => $this->putJson(route('radio-management.service.note', $ticket->id), ['note' => $text]);
        $move('sent')->assertStatus(422)->assertJsonPath('errors.0', 'Service center is required.');
        $move('sent', ['service_center' => 'Pusat', 'sent_at' => '2026-10-21'])->assertStatus(422)->assertJsonPath('errors.0', 'The date sent cannot be after today.');
        $move('sent', ['service_center' => str_repeat('x', 151)])->assertStatus(422)->assertJsonPath('errors.0', 'Service center can be at most 150 characters.');
        $move('sent', ['service_center' => 'Pusat', 'quote' => -5])->assertStatus(422)->assertJsonPath('errors.0', 'Quote cannot be less than 0.');
        $move('gone')->assertStatus(422)->assertJsonPath('errors.0', 'Status is not a known choice.');
        $move('ready')->assertStatus(422)->assertJsonPath('errors.0', 'Someone else has moved this ticket on. Reload the page.');

        $move('sent', ['service_center' => 'Pusat Servis Contoh', 'document_no' => 'BA2300418', 'quote' => 1850000])->assertOk()
            ->assertJsonPath('message', 'Ticket SRV-0001: sent for service.');
        $move('in_repair', ['quote' => 2100000])->assertOk()->assertJsonPath('message', 'Ticket SRV-0001: in repair.');
        $move('ready')->assertOk()->assertJsonPath('message', 'Ticket SRV-0001: ready for pickup.');
        $note('Menunggu suku cadang')->assertOk()->assertJsonPath('message', 'Note added to SRV-0001.');
        $note(' ')->assertStatus(422)->assertJsonPath('errors.0', 'Write the note.');

        // The board and the drawer: the step and the radio by their English names, the activity in English around what
        // was written into it.
        $this->getJson(route('radio-management.service.list'))->assertOk()->assertJsonPath('data.0.status_label', 'Ready for pickup');
        $shown = $this->getJson(route('radio-management.service.show', $ticket->id))->assertOk()->json('data');
        $this->assertSame(['In service', 'Needs service'], [$shown['radio_now']['placement'], $shown['radio_now']['condition']]);
        $this->assertSame(['Menunggu suku cadang', 'Ready for pickup', 'Repair started · quote Rp 2.100.000', 'Sent to Pusat Servis Contoh · BA BA2300418', 'Reported: LCD retak'],
            array_column($shown['events'], 'note'));

        $move('returned', ['placement' => 'stok_it', 'condition' => 'layak'])->assertOk()->assertJsonPath('message', 'Ticket SRV-0001: returned.');
        $this->assertSame('Returned: IT stock · Good', $this->getJson(route('radio-management.service.show', $ticket->id))->json('data.events.0.note'));
        $move('returned', ['placement' => 'stok_it', 'condition' => 'layak'])->assertStatus(422)->assertJsonPath('errors.0', 'This ticket is finished: the radio is back.');

        // Written down in Indonesian, as before.
        $this->assertSame(['Dilaporkan: LCD retak', 'Dikirim ke Pusat Servis Contoh · BA BA2300418', 'Mulai diperbaiki · penawaran Rp 2.100.000', 'Siap diambil',
            'Menunggu suku cadang', 'Kembali: Stok IT · Layak'], ServiceTicketEvent::query()->orderBy('id')->pluck('note')->all());
        $this->assertSame(['Dikirim ke Pusat Servis Contoh (SRV-0001): LCD retak', 'Kembali dari Pusat Servis Contoh (SRV-0001). Kondisi: Layak.'],
            RadioHistory::query()->where('radio_id', $radio->id)->where('action', 'Service')->orderBy('id')->pluck('description')->all());
        $this->assertSame('Dikirim ke servis', AuditLog::query()->where('action', 'service.status_changed')->orderBy('id')->first()->new_values['Status']);
        $export = new ServiceTicketExport(app(ServiceTickets::class)->search(null, null)->get());
        $this->assertSame(['No. Tiket', 'Kembali'], [$export->headings()[0], $export->map($export->collection()->sole())[6]]);
    }

    public function test_an_activity_line_shows_in_the_words_of_its_form_or_as_written(): void
    {
        $line = fn (string $status, string $note, string $kind = ServiceTicketEvent::STATUS) => new ServiceTicketEvent(['kind' => $kind, 'status' => $status, 'note' => $note]);

        $this->assertSame('Sent to Pusat · BA 12', ServiceTickets::eventText($line('sent', 'Dikirim ke Pusat · BA 12')));
        $this->assertSame('Sent to Pusat A', ServiceTickets::eventText($line('sent', 'Dikirim ke Pusat A')));
        $this->assertSame('Ready for pickup · cost Rp 850.000', ServiceTickets::eventText($line('ready', 'Siap diambil · biaya Rp 850.000')));
        // In Indonesian, as written.
        $this->assertSame('Dikirim ke Pusat · BA 12', ServiceTickets::eventText($line('sent', 'Dikirim ke Pusat · BA 12'), 'id'));
        // What was written into it stays as it was, also what looks like a placeholder or a pattern.
        $this->assertSame("Reported: :issue (.*) \$1\nline two", ServiceTickets::eventText($line('reported', "Dilaporkan: :issue (.*) \$1\nline two")));
        // A location that is not one of the register's is left as written.
        $this->assertSame('Returned: Gudang · Good', ServiceTickets::eventText($line('returned', 'Kembali: Gudang · Layak')));
        // A line in none of the forms of its step, and a note, show as written.
        $this->assertSame('Dikirim lewat kurir', ServiceTickets::eventText($line('sent', 'Dikirim lewat kurir')));
        $this->assertSame('Dilaporkan: LCD retak', ServiceTickets::eventText($line('sent', 'Dilaporkan: LCD retak')));
        $this->assertSame('Dilaporkan: bukan langkah', ServiceTickets::eventText($line('reported', 'Dilaporkan: bukan langkah', ServiceTicketEvent::NOTE)));
    }

    public function test_before_the_migration_has_run_the_page_says_so_in_english(): void
    {
        Schema::drop('service_ticket_events');
        Schema::drop('service_tickets');
        Cache::forget('service-tickets-table');
        $this->actingAs($this->superAdmin());

        $text = 'The service ticket tables are not in the database yet. Run "php artisan migrate" first.';
        $this->assertStringContainsString(e($text), (string) $this->get(route('radio-management.service.index'))->assertOk()->getContent());
        $this->getJson(route('radio-management.service.list'))->assertStatus(503)->assertJsonPath('message', $text);
    }

    public function test_the_script_of_the_page_writes_english(): void
    {
        $this->checkPageScript(route('radio-management.service.index'), 'tests/tools/service-board-english-behaviour.mjs', 6);
    }
}
