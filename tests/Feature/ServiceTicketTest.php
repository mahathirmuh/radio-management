<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use Database\Seeders\RadioSectionMenuSeeder;
use Database\Seeders\ServiceMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Exports\ServiceTicketExport;
use Modules\RadioManagement\Http\Controllers\ServiceTicketController;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Models\ServiceTicket;
use Modules\RadioManagement\Models\ServiceTicketEvent;
use Modules\RadioManagement\Services\RadioImportService;
use RuntimeException;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * "Servis & Perbaikan" (6.3): a ticket for a radio that goes for repair, moved on a step at a time. Sent, the radio is at
 * "Service" with a line in its history; back, it is where it is put. The menu sits right below Register Radio.
 */
class ServiceTicketTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    private const MIGRATION = 'migrations/2026_10_20_110000_add_service_menu.php';

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

    /** Opens a ticket through the page, as Super Admin unless somebody else is signed in. */
    private function open(Radio $radio, array $other = [])
    {
        return $this->postJson(route('radio-management.service.store'), $other + ['radio_id' => $radio->id, 'issue' => 'LCD retak']);
    }

    private function move(ServiceTicket $ticket, string $status, array $other = [])
    {
        return $this->putJson(route('radio-management.service.status', $ticket->id), ['status' => $status] + $other);
    }

    /** The root entries of the sidebar from Register Radio down, in their order. */
    private function sidebar(): array
    {
        $register = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('order');

        return DB::table('menu')->whereNull('parent_id')->where('order', '>=', $register)->orderBy('order')->limit(5)
            ->pluck('order', 'title')->map(fn ($place) => (int) $place)->all();
    }

    // ================================================================ the menu

    public function test_the_menu_is_right_below_register_radio_and_given_like_it(): void
    {
        $menu = DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->sole();
        $this->assertSame(['Servis & Perbaikan', 'tool', null, null], [$menu->title, $menu->icon, $menu->parent_id, $menu->section]);
        $register = DB::table('menu')->where('route', 'radio-management.radio.index')->sole();
        // Riwayat Radio, the group of the master data and Import Excel moved down to make room.
        $this->assertSame([
            'Register Radio' => (int) $register->order,
            'Servis & Perbaikan' => $register->order + 1,
            'Riwayat Radio' => $register->order + 2,
            'Master Data Radio' => $register->order + 3,
            'Import Excel' => $register->order + 4,
        ], $this->sidebar());

        // The same roles and permissions as Register Radio: Admin Radio all, Viewer Radio Read, Super Admin all.
        $grants = fn (int $menuId) => DB::table('menu_role_permission')->where('menu_id', $menuId)->orderBy('role_id')->orderBy('permission_id')
            ->get()->map(fn ($grant) => [(int) $grant->role_id, (int) $grant->permission_id])->all();
        $this->assertSame($grants((int) $register->id), $grants((int) $menu->id));
        $viewer = (int) DB::table('roles')->where('name', 'Viewer Radio')->value('id');
        $this->assertContains([$viewer, CheckMenuPermission::PERMISSION_IDS['Read']], $grants((int) $menu->id));
        $this->assertNotContains([$viewer, CheckMenuPermission::PERMISSION_IDS['Create']], $grants((int) $menu->id));

        // Seeding again (a database in use: RadioMenuSeeder puts the section in order again) adds nothing and moves nothing.
        $before = $this->sidebar();
        $this->seed();
        $this->assertSame(1, DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->count());
        $this->assertSame($before, $this->sidebar());

        // In the sidebar of Admin Radio, below Register Radio.
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#' . preg_quote(route('radio-management.radio.index'), '#') . '".*?' . preg_quote(route('radio-management.service.index'), '#') . '".*?Servis &amp; Perbaikan#s', $page);
    }

    public function test_the_migration_adds_the_entry_to_a_database_in_use_and_takes_it_away_again(): void
    {
        $migration = require database_path(self::MIGRATION);
        $register = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('order');
        $orders = fn () => DB::table('menu')->whereNull('parent_id')
            ->where(fn ($query) => $query->whereNull('route')->orWhere('route', '<>', ServiceMenuSeeder::ROUTE))
            ->orderBy('id')->pluck('order', 'id')->map(fn ($place) => (int) $place)->all();
        $with = $orders();

        // Rolled back: the entry and its grants are gone, and what is below it moves up again.
        $migration->down();
        $this->assertFalse(DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->exists());
        $this->assertSame(0, DB::table('menu_role')->whereNotIn('menu_id', DB::table('menu')->select('id'))->count());
        $this->assertSame(['Register Radio' => $register, 'Riwayat Radio' => $register + 1], array_slice($this->sidebar(), 0, 2, true));
        $this->assertSame(array_map(fn (int $place) => $place > $register ? $place - 1 : $place, $with), $orders());

        // Migrated again: back in its place, with the grants of Register Radio.
        $migration->up();
        $this->assertSame(['Register Radio' => $register, 'Servis & Perbaikan' => $register + 1, 'Riwayat Radio' => $register + 2], array_slice($this->sidebar(), 0, 3, true));
        $this->assertSame($with, $orders());
        $this->assertTrue(DB::table('menu_role')->where('menu_id', DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->value('id'))->where('role_id', 1)->exists());

        // Renamed by the owner, it is left alone.
        DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->update(['title' => 'Perbaikan Radio']);
        $migration->up();
        $this->assertSame(['Perbaikan Radio'], DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->pluck('title')->all());
    }

    // ================================================================ the page and who may do what

    public function test_the_page_and_its_buttons_follow_the_permissions(): void
    {
        $radio = $this->radio();

        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.service.index'))->assertOk()->getContent();
        foreach (['Servis &amp; Perbaikan', 'id="serviceBoard"', 'id="serviceTable"', 'id="serviceNew"', 'id="serviceNextButton"', 'id="serviceMoveModal"', 'id="serviceNoteModal"', 'Semua pusat servis'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        $this->assertStringNotContainsString('serviceNotReady', $page);

        // Viewer Radio reads (the board, a ticket, the export) and changes nothing.
        $ticket = ServiceTicket::query()->findOrFail($this->open($radio)->assertCreated()->json('data.id'));
        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $page = (string) $this->get(route('radio-management.service.index'))->assertOk()->getContent();
        foreach (['id="serviceNew"', 'id="serviceNextButton"', 'id="serviceMoveModal"', 'id="serviceNoteModal"'] as $part) {
            $this->assertStringNotContainsString($part, $page, $part);
        }
        $this->getJson(route('radio-management.service.list'))->assertOk()->assertJsonPath('data.0.number', 'SRV-0001');
        $this->getJson(route('radio-management.service.show', $ticket->id))->assertOk();
        $this->get(route('radio-management.service.export'))->assertOk();
        $this->open($this->radio(['dmr_id' => 752, 'serial_number' => '903KXM2012']))->assertForbidden();
        $this->getJson(route('radio-management.service.radios', ['q' => '751']))->assertForbidden();
        $this->move($ticket, 'sent', ['service_center' => 'Pusat Servis Contoh'])->assertForbidden();
        $this->putJson(route('radio-management.service.note', $ticket->id), ['note' => 'Coba'])->assertForbidden();

        // Admin Radio opens and moves tickets.
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $this->assertStringContainsString('id="serviceNew"', (string) $this->get(route('radio-management.service.index'))->getContent());
        $this->move($ticket, 'sent', ['service_center' => 'Pusat Servis Contoh'])->assertOk();

        // Without its menu row the page is closed to everyone but Super Admin (it changes radios).
        RadioSectionMenuSeeder::remove(ServiceMenuSeeder::ROUTE);
        $this->get(route('radio-management.service.index'))->assertForbidden();
        $this->getJson(route('radio-management.service.list'))->assertForbidden();
        $this->actingAs($this->superAdmin());
        $this->get(route('radio-management.service.index'))->assertOk();
    }

    // ================================================================ opening a ticket

    public function test_a_ticket_gets_the_next_number_and_a_radio_has_one_open_ticket(): void
    {
        $radio = $this->radio();
        $this->actingAs($this->superAdmin());

        $this->open($radio)->assertCreated()->assertJsonPath('message', 'Tiket SRV-0001 dibuat.');
        $ticket = ServiceTicket::query()->sole();
        $this->assertSame(
            ['SRV-0001', $radio->id, 'ID 751 · 903KXM2011', 751, '903KXM2011', 'XiR P8668i', 'reported', 'LCD retak', 'Pemakai Contoh · Laboratory'],
            [$ticket->number, $ticket->radio_id, $ticket->radio_label, $ticket->radio_dmr_id, $ticket->radio_sn, $ticket->radio_type, $ticket->status, $ticket->issue, $ticket->reported_by]
        );
        $this->assertSame(['status', 'reported', 'Dilaporkan: LCD retak'], ServiceTicketEvent::query()->get(['kind', 'status', 'note'])->map(fn ($event) => [$event->kind, $event->status, $event->note])->sole());

        $audit = AuditLog::query()->where('action', 'service.created')->sole();
        $this->assertSame(['ServiceTicket', $ticket->id, 'SRV-0001 · ID 751 · 903KXM2011'], [$audit->entity_type, (int) $audit->entity_id, $audit->entity_label]);
        $this->assertSame(['Radio' => 'ID 751 · 903KXM2011', 'Masalah' => 'LCD retak', 'Dilaporkan oleh' => 'Pemakai Contoh · Laboratory'], $audit->new_values);

        // One open ticket per radio.
        $this->open($radio)->assertStatus(422)->assertJsonPath('errors.0', 'Radio ini masih punya tiket servis yang terbuka: SRV-0001.');
        // The next radio gets the next number; who reported it may be typed.
        $other = $this->radio(['dmr_id' => 752, 'serial_number' => '903KXM2012']);
        $this->open($other, ['reported_by' => 'LV 12 · Light vehicle'])->assertCreated()->assertJsonPath('data.number', 'SRV-0002');
        $this->assertSame('LV 12 · Light vehicle', ServiceTicket::query()->where('number', 'SRV-0002')->value('reported_by'));

        // An archived radio, one that is not there, a ticket without its issue: refused.
        $archived = $this->radio(['dmr_id' => null, 'serial_number' => '903KXM2013', 'archived_at' => now()->toDateString(), 'archive_reason' => 'Afkir']);
        $this->open($archived)->assertStatus(422)->assertJsonPath('errors.0', 'Radio yang diarsipkan tidak bisa diservis. Pulihkan dulu.');
        $this->open($radio, ['radio_id' => 999999])->assertStatus(422)->assertJsonPath('errors.0', 'Radio itu tidak ada di register.');
        $this->open($other, ['issue' => ''])->assertStatus(422)->assertJsonPath('errors.0', 'Tulis masalah radionya.');
        $this->assertSame(2, ServiceTicket::query()->count());
    }

    public function test_the_radios_for_a_new_ticket_are_looked_up_in_the_register(): void
    {
        $radio = $this->radio();
        $this->radio(['dmr_id' => 7510, 'serial_number' => '903KXM7510', 'user_name' => 'Orang Lain']);
        $this->radio(['dmr_id' => 7511, 'serial_number' => '903KXM7511', 'archived_at' => now()->toDateString(), 'archive_reason' => 'Afkir']);
        $this->actingAs($this->superAdmin());
        $this->open($radio)->assertCreated();

        // The Radio ID typed first; an archived radio is not offered; one with an open ticket says which.
        $found = $this->getJson(route('radio-management.service.radios', ['q' => '751']))->assertOk()->json('data');
        $this->assertSame([[751, 'SRV-0001', 'Pemakai Contoh · Laboratory', 'Di User'], [7510, null, 'Orang Lain · Laboratory', 'Di User']],
            array_map(fn ($one) => [$one['dmr_id'], $one['open'], $one['holder'], $one['placement']], $found));
        $this->assertSame(['903KXM7510'], array_column($this->getJson(route('radio-management.service.radios', ['q' => 'orang lain']))->json('data'), 'sn'));
        $this->assertSame([], $this->getJson(route('radio-management.service.radios', ['q' => '']))->json('data'));
        // Searched as text: a pattern of LIKE matches nothing more.
        $this->assertSame([], $this->getJson(route('radio-management.service.radios', ['q' => '%']))->json('data'));
    }

    // ================================================================ the steps

    public function test_sending_puts_the_radio_at_service_and_coming_back_puts_it_where_it_is_told(): void
    {
        // A week passes in one signed-in session.
        $this->withoutSessionTimeouts();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $radio = $this->radio();
        $this->actingAs($this->superAdmin());
        $ticket = ServiceTicket::query()->findOrFail($this->open($radio)->json('data.id'));

        $this->move($ticket, 'sent', [
            'service_center' => 'Pusat Servis Contoh',
            'sent_at' => '2026-10-20',
            'document_no' => 'BA2300418',
            'expected_at' => '2026-10-28',
            'quote' => 1850000,
        ])->assertOk()->assertJsonPath('message', 'Tiket SRV-0001: dikirim ke servis.');

        $radio->refresh();
        $ticket->refresh();
        $this->assertSame('service', $radio->placement->value);
        $this->assertSame(['sent', 'Pusat Servis Contoh', '2026-10-20', 'BA2300418', '2026-10-28', 1850000, 'di_user'],
            [$ticket->status, $ticket->service_center, $ticket->sent_at->toDateString(), $ticket->document_no, $ticket->expected_at->toDateString(), $ticket->quote, $ticket->placement_before]);
        $sent = RadioHistory::query()->where('radio_id', $radio->id)->where('action', 'Service')->sole();
        $this->assertSame(['2026-10-20', 'BA2300418', 'Dikirim ke Pusat Servis Contoh (SRV-0001): LCD retak'], [$sent->event_date->toDateString(), $sent->document_no, $sent->description]);
        $this->assertSame(['label' => 'Posisi', 'old' => 'Di User', 'new' => 'Service'], $sent->changes['placement']);

        $audit = AuditLog::query()->where('action', 'service.status_changed')->sole();
        // (Next to a value on the other side, an empty one says something: "Pusat servis: - → Pusat Servis Contoh".)
        $this->assertSame(['Status' => 'Dilaporkan', 'Pusat servis' => null, 'Tanggal kirim' => null, 'No. BA' => null, 'Perkiraan kembali' => null, 'Penawaran' => null], $audit->old_values);
        $this->assertSame([
            'Status' => 'Dikirim ke servis',
            'Pusat servis' => 'Pusat Servis Contoh',
            'Tanggal kirim' => '20-10-2026',
            'No. BA' => 'BA2300418',
            'Perkiraan kembali' => '28-10-2026',
            'Penawaran' => 'Rp 1.850.000',
        ], $audit->new_values);

        // Only the next step: one that was taken by somebody else meanwhile is refused.
        $this->move($ticket, 'ready')->assertStatus(422)->assertJsonPath('errors.0', 'Tiket ini sudah dipindah orang lain. Muat ulang halaman.');
        $this->move($ticket, 'in_repair', ['quote' => 2100000])->assertOk();
        $this->move($ticket, 'ready', ['cost' => 2050000])->assertOk();
        $this->assertSame(1, RadioHistory::query()->where('action', 'Service')->count(), 'the steps at the service center do not touch the radio');

        Carbon::setTestNow('2026-10-27 09:00:00');
        $this->move($ticket, 'returned', ['placement' => 'stok_it', 'condition' => 'layak'])->assertOk();
        $radio->refresh();
        $ticket->refresh();
        $this->assertSame(['stok_it', 'layak'], [$radio->placement->value, $radio->condition->value]);
        $this->assertSame(['returned', '2026-10-27', 2100000, 2050000, 7], [$ticket->status, $ticket->returned_at->toDateString(), $ticket->quote, $ticket->cost, $ticket->daysAway()]);
        $back = RadioHistory::query()->where('action', 'Service')->latest('id')->first();
        $this->assertSame(['2026-10-27', 'Kembali dari Pusat Servis Contoh (SRV-0001). Kondisi: Layak.'], [$back->event_date->toDateString(), $back->description]);

        $this->assertSame(
            ['Kembali: Stok IT · Layak', 'Siap diambil · biaya Rp 2.050.000', 'Mulai diperbaiki · penawaran Rp 2.100.000', 'Dikirim ke Pusat Servis Contoh · BA BA2300418', 'Dilaporkan: LCD retak'],
            $ticket->events()->pluck('note')->all()
        );
        $this->move($ticket, 'returned', ['placement' => 'stok_it', 'condition' => 'layak'])->assertStatus(422)->assertJsonPath('errors.0', 'Tiket ini sudah selesai: radionya sudah kembali.');
        // The radio is free for a new ticket again.
        $this->open($radio)->assertCreated()->assertJsonPath('data.number', 'SRV-0002');
    }

    public function test_the_steps_check_what_they_are_given(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->actingAs($this->superAdmin());
        $ticket = ServiceTicket::query()->findOrFail($this->open($this->radio())->json('data.id'));

        $this->move($ticket, 'sent')->assertStatus(422)->assertJsonPath('errors.0', 'Pusat servis wajib diisi.');
        $this->move($ticket, 'sent', ['service_center' => 'Pusat', 'sent_at' => '2026-10-21'])->assertStatus(422)->assertJsonPath('errors.0', 'Tanggal kirim tidak boleh lewat dari hari ini.');
        $this->move($ticket, 'sent', ['service_center' => 'Pusat', 'sent_at' => '2026-10-19'])->assertStatus(422)->assertJsonPath('errors.0', 'Tanggal kirim tidak boleh sebelum tiket dibuat.');
        $this->move($ticket, 'sent', ['service_center' => 'Pusat', 'expected_at' => '2026-10-19'])->assertStatus(422)->assertJsonPath('errors.0', 'Perkiraan kembali tidak boleh sebelum tanggal kirim.');
        $this->move($ticket, 'sent', ['service_center' => 'Pusat', 'quote' => -5])->assertStatus(422)->assertJsonPath('errors.0', 'Penawaran tidak boleh kurang dari 0.');
        $this->move($ticket, 'sent', ['service_center' => str_repeat('x', 151)])->assertStatus(422)->assertJsonPath('errors.0', 'Pusat servis paling panjang 150 huruf.');
        $this->move($ticket, 'gone')->assertStatus(422)->assertJsonPath('errors.0', 'Pilihan Status tidak dikenal.');
        $this->assertSame('reported', $ticket->fresh()->status);

        $this->move($ticket, 'sent', ['service_center' => 'Pusat'])->assertOk();
        $this->assertSame('2026-10-20', $ticket->fresh()->sent_at->toDateString(), 'sent today when no day is given');
        $this->move($ticket, 'in_repair')->assertOk();
        $this->move($ticket, 'ready')->assertOk();
        // Back where it may go: with a user or in IT's stock; and in a condition that exists.
        $this->move($ticket, 'returned', ['placement' => 'service', 'condition' => 'layak'])->assertStatus(422);
        $this->move($ticket, 'returned', ['placement' => 'di_user', 'condition' => 'baru'])->assertStatus(422);
        $this->move($ticket, 'returned', ['condition' => 'layak'])->assertStatus(422);
        $this->assertSame('ready', $ticket->fresh()->status);
    }

    // ================================================================ what the page shows

    public function test_the_board_lists_the_open_tickets_with_their_days_and_the_numbers_above_them(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $make = fn (string $number, string $status, string $created, array $other = []) => ServiceTicket::query()->forceCreate($other + [
            'number' => $number, 'radio_label' => "Radio {$number}", 'status' => $status, 'issue' => "Masalah {$number}",
            'created_at' => $created, 'updated_at' => $created,
        ]);
        $make('SRV-0001', 'returned', '2026-04-20', ['sent_at' => '2026-04-21', 'returned_at' => '2026-05-01', 'cost' => 900000, 'service_center' => 'Pusat B']);
        $make('SRV-0002', 'returned', '2026-08-30', ['sent_at' => '2026-09-01', 'returned_at' => '2026-09-10', 'cost' => 500000, 'service_center' => 'Pusat A']);
        $make('SRV-0003', 'returned', '2026-09-30', ['sent_at' => '2026-10-01', 'returned_at' => '2026-10-08', 'cost' => 1850000, 'service_center' => 'Pusat A']);
        $make('SRV-0004', 'reported', '2026-10-16');
        $make('SRV-0005', 'sent', '2026-09-29', ['sent_at' => '2026-09-30', 'service_center' => 'Pusat A']);
        $make('SRV-0006', 'in_repair', '2026-10-09', ['sent_at' => '2026-10-10', 'service_center' => 'Pusat B']);
        $make('SRV-0007', 'ready', '2026-10-03', ['sent_at' => '2026-10-04', 'service_center' => 'Pusat A']);

        $this->actingAs($this->superAdmin());
        $response = $this->getJson(route('radio-management.service.list'))->assertOk();
        // The open ones, the newest first: days since sent (since reported while not sent), overdue past 14 days away.
        $this->assertSame([
            ['SRV-0007', 'ready', 16, true],
            ['SRV-0006', 'in_repair', 10, false],
            ['SRV-0005', 'sent', 20, true],
            ['SRV-0004', 'reported', 4, false],
        ], array_map(fn ($row) => [$row['number'], $row['status'], $row['days'], $row['overdue']], $response->json('data')));
        // Back in the last 90 days: 9 and 7 days away (the one from May is older); closed this quarter: one, Rp 1.850.000.
        $this->assertSame(['open' => 4, 'reported' => 1, 'away' => 3, 'overdue' => 2, 'turnaround' => 8, 'closed' => 1, 'cost' => 1850000], $response->json('stats'));
        $this->assertSame(['Pusat A', 'Pusat B'], $response->json('centers'));

        // A ticket of its own: the next step, what happened, the radio as it is now.
        $radio = $this->radio();
        $ticket = ServiceTicket::query()->findOrFail($this->open($radio)->json('data.id'));
        $this->putJson(route('radio-management.service.note', $ticket->id), ['note' => "Menunggu <b>suku cadang</b>\nbaris dua"])->assertOk()
            ->assertJsonPath('message', 'Catatan ditambahkan ke SRV-0008.');
        $shown = $this->getJson(route('radio-management.service.show', $ticket->id))->assertOk()->json('data');
        $this->assertSame(['SRV-0008', '751', 'sent', 'Di User', route('radio-management.radio.detail', $radio)], [$shown['number'], $shown['radio'], $shown['next'], $shown['radio_now']['placement'], $shown['radio_now']['url']]);
        $this->assertSame([['note', null, "Menunggu <b>suku cadang</b>\nbaris dua"], ['status', 'reported', 'Dilaporkan: LCD retak']],
            array_map(fn ($event) => [$event['kind'], $event['status'], $event['note']], $shown['events']));
        $this->assertSame(['Catatan' => "Menunggu <b>suku cadang</b>\nbaris dua"], AuditLog::query()->where('action', 'service.note_added')->sole()->new_values);
        $this->putJson(route('radio-management.service.note', $ticket->id), ['note' => ' '])->assertStatus(422)->assertJsonPath('errors.0', 'Tulis catatannya.');

        // A radio gone from the register: the ticket says so, without a link.
        $radio->delete();
        $this->assertSame(['gone' => true, 'url' => null], array_intersect_key($this->getJson(route('radio-management.service.show', $ticket->id))->json('data.radio_now'), ['gone' => 1, 'url' => 1]));
        $this->getJson(route('radio-management.service.show', 999999))->assertNotFound();
    }

    public function test_the_export_holds_every_ticket_that_matches(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->actingAs($this->superAdmin());
        $first = ServiceTicket::query()->findOrFail($this->open($this->radio())->json('data.id'));
        $this->move($first, 'sent', ['service_center' => 'Pusat A', 'document_no' => '=1+1', 'quote' => 450000])->assertOk();
        $this->open($this->radio(['dmr_id' => 752, 'serial_number' => '903KXM2012']), ['issue' => 'Baterai cepat habis'])->assertCreated();

        $response = $this->get(route('radio-management.service.export', ['center' => 'Pusat A']));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));

        $export = new ServiceTicketExport(app(\Modules\RadioManagement\Services\ServiceTickets::class)->search('baterai', null)->get());
        $this->assertSame(['No. Tiket', 'Radio ID', 'SN', 'Tipe Radio', 'Masalah', 'Dilaporkan oleh', 'Status', 'Tgl Lapor', 'Pusat Servis', 'Tgl Kirim', 'No. BA',
            'Perkiraan Kembali', 'Tgl Kembali', 'Hari di Servis', 'Penawaran (Rp)', 'Biaya (Rp)'], $export->headings());
        $this->assertSame([['SRV-0002', 752, '903KXM2012', 'XiR P8668i', 'Baterai cepat habis', 'Pemakai Contoh · Laboratory', 'Dilaporkan', '20-10-2026', null, null, null, null, null, null, null, null]],
            $export->collection()->map(fn ($ticket) => $export->map($ticket))->all());
        $sent = app(\Modules\RadioManagement\Services\ServiceTickets::class)->search(null, 'Pusat A')->get();
        $this->assertSame([['SRV-0001', '=1+1', 0, 450000]], $sent->map(fn ($ticket) => [$ticket->number, $ticket->document_no, $ticket->daysAway(), $ticket->quote])->all());
        $this->assertSame(['SRV-0001'], app(\Modules\RadioManagement\Services\ServiceTickets::class)->search('751', null)->pluck('number')->all());
    }

    // ================================================================ around it

    public function test_an_import_that_starts_over_waits_until_every_radio_is_back(): void
    {
        $this->actingAs($this->superAdmin());
        $this->open($this->radio())->assertCreated();

        try {
            app(RadioImportService::class)->import(base_path('tidak-ada.xlsx'), ['fresh' => true]);
            $this->fail('A fresh import went ahead with a ticket open.');
        } catch (RuntimeException $refused) {
            $this->assertSame('Masih ada 1 tiket servis yang terbuka. Impor yang menghapus data lama baru bisa dijalankan setelah semua radio itu kembali (menu Servis & Perbaikan).', $refused->getMessage());
        }
        $this->assertSame(1, Radio::query()->count());
    }

    public function test_before_the_migration_has_run_the_page_says_so(): void
    {
        Schema::drop('service_ticket_events');
        Schema::drop('service_tickets');
        Cache::forget('service-tickets-table');
        $this->actingAs($this->superAdmin());

        $page = (string) $this->get(route('radio-management.service.index'))->assertOk()->getContent();
        $this->assertStringContainsString(e(ServiceTicketController::NOT_READY), $page);
        $this->getJson(route('radio-management.service.list'))->assertStatus(503)->assertJsonPath('message', ServiceTicketController::NOT_READY);
        $this->open($this->radio())->assertStatus(503);
        // Nothing else waits for the tables: a fresh import is not held up by tickets that cannot exist.
        $this->assertSame(0, ServiceTicket::openCount());
    }

    public function test_the_script_of_the_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('radio-management.service.index'), 'tests/tools/service-board-behaviour.mjs', 12);
    }
}
