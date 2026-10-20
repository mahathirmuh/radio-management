<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use Database\Seeders\OpnameMenuSeeder;
use Database\Seeders\RadioSectionMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Http\Controllers\OpnameController;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioOpnameItem;
use Modules\RadioManagement\Models\RadioOpnameSession;
use Modules\RadioManagement\Models\ServiceTicket;
use Modules\RadioManagement\Services\RadioImportService;
use RuntimeException;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * "Stock Opname" (6.5): the radios of a department counted on a phone. Every scan is saved at once; sending the session
 * verifies the radios found today (a history line each, the condition put in when it changed) with one audit line.
 */
class OpnameTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    private RadioDepartment $plant;

    private RadioDepartment $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = RadioDepartment::query()->firstOrCreate(['name' => 'Bagian Pabrik'], ['is_active' => true]);
        $this->lab = RadioDepartment::query()->firstOrCreate(['name' => 'Bagian Lab'], ['is_active' => true]);
    }

    private function radio(int $id, array $other = []): Radio
    {
        return Radio::create($other + [
            'dmr_id' => $id,
            'serial_number' => sprintf('867TZB%04d', $id),
            'user_name' => "Pemakai {$id}",
            'department_id' => $this->plant->id,
            'placement' => 'di_user',
            'condition' => 'layak',
            'last_verified_at' => '2025-01-15',
        ]);
    }

    /** Radios of the plant (two expected), one of the lab. */
    private function radios(): array
    {
        return [
            'here' => $this->radio(1042),
            'stock' => $this->radio(1043, ['placement' => 'stok_it', 'user_name' => null]),
            'away' => $this->radio(1044, ['placement' => 'service']),
            'lost' => $this->radio(1045, ['placement' => 'hilang', 'document_no' => 'BA-1']),
            'archived' => $this->radio(1046, ['dmr_id' => null, 'archived_at' => '2026-01-01', 'archive_reason' => 'Afkir']),
            'lab' => $this->radio(1047, ['department_id' => $this->lab->id, 'user_name' => 'Pemakai Lab']),
        ];
    }

    private function start(?int $department = null)
    {
        return $this->postJson(route('radio-management.opname.start'), ['department_id' => $department ?? $this->plant->id]);
    }

    private function scan(int $session, Radio $radio, array $other = [], string $route = 'radio-management.opname.scan')
    {
        return $this->putJson(route($route, $session), $other + ['radio_id' => $radio->id, 'holder' => 'register', 'condition' => 'layak']);
    }

    /** A user whose only role has these permissions on Stock Opname (and none elsewhere). */
    private function withOpname(array $permissions, string $email): User
    {
        $roleId = (int) DB::table('roles')->insertGetId(['name' => 'Peran ' . $email, 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $menuId = DB::table('menu')->where('route', OpnameMenuSeeder::ROUTE)->value('id');
        DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('menu_role_permission')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS[$permission], 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::forget('rbac_menu_route_key_map');

        return $this->makeUser($email, $roleId, 'Penghitung');
    }

    // ================================================================ the menu and the page

    public function test_the_menu_is_below_servis_and_the_page_is_for_a_phone(): void
    {
        $register = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('order');
        $this->assertSame(['Register Radio', 'Perlu Perhatian', 'Servis & Perbaikan', 'Stock Opname', 'Riwayat Radio'],
            DB::table('menu')->whereNull('parent_id')->where('order', '>=', $register)->orderBy('order')->limit(5)->pluck('title')->all());
        $this->assertSame($register + 3, (int) DB::table('menu')->where('route', OpnameMenuSeeder::ROUTE)->value('order'));
        $this->assertSame('barcode', DB::table('menu')->where('route', OpnameMenuSeeder::ROUTE)->value('icon'));

        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.opname.index'))->assertOk()
            ->assertViewHas('may', ['start' => true, 'scan' => true, 'report' => true])->getContent();
        foreach (['id="opnStart"', 'id="opnScan"', 'id="opnVerify"', 'id="opnSummary"', 'Bagian Pabrik', 'Semua company', 'Arahkan ke barcode SN di radio'] as $part) {
            $this->assertStringContainsString($part, $page, $part);
        }
        // A page of its own: no sidebar, no top bar.
        $this->assertStringNotContainsString('rm-sidebar', $page);
        $this->assertStringNotContainsString('rm-topbar', $page);
        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($page));

        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->get(route('radio-management.opname.index'))->assertOk()->assertViewHas('may', ['start' => false, 'scan' => false, 'report' => false]);

        // The migration takes it away and puts it back below Servis & Perbaikan.
        $migration = require database_path('migrations/2026_10_20_150000_add_opname_menu.php');
        $migration->down();
        $this->assertFalse(DB::table('menu')->where('route', OpnameMenuSeeder::ROUTE)->exists());
        $this->assertSame($register + 3, (int) DB::table('menu')->where('route', 'radio-management.history.index')->value('order'));
        $migration->up();
        $this->assertSame($register + 3, (int) DB::table('menu')->where('route', OpnameMenuSeeder::ROUTE)->value('order'));
    }

    // ================================================================ a session

    public function test_a_session_counts_the_radios_of_a_department_and_saves_every_scan(): void
    {
        $radios = $this->radios();
        $this->actingAs($this->superAdmin());

        // Expected: the active radios of the department, not those away for repair or lost.
        $this->getJson(route('radio-management.opname.outlook', ['department_id' => $this->plant->id]))->assertOk()
            ->assertJsonPath('data', ['expected' => 2, 'last' => null, 'open' => null]);
        $this->getJson(route('radio-management.opname.outlook'))->assertStatus(422)->assertJsonPath('errors.0', 'Pilih department yang dihitung.');

        $state = $this->start()->assertCreated()->json('data');
        $session = $state['session']['id'];
        $this->assertSame(['Bagian Pabrik', 'open', 2, 0, 0], [$state['session']['place'], $state['session']['status'], $state['expected'], $state['found'], $state['scanned']]);
        $this->assertSame(['1042 · MDC 412', '1043 · MDC 413'], array_column($state['not_found'], 'radio'));
        // Starting again there goes on with it.
        $this->start()->assertOk()->assertJsonPath('data.session.id', $session);
        $this->getJson(route('radio-management.opname.outlook', ['department_id' => $this->plant->id]))->assertJsonPath('data.open', $session);

        // By Radio ID, by SN as it is typed; one of another department says so; one that is not there, 404.
        $lookup = fn (string $q) => $this->getJson(route('radio-management.opname.lookup', ['opname' => $session, 'q' => $q]));
        $card = $lookup('1042')->assertOk()->json('data');
        $this->assertSame([$radios['here']->id, 1042, '412', '867TZB1042', true, null, false], [$card['id'], $card['dmr_id'], $card['mdc'], $card['sn'], $card['here'], $card['scanned'], $card['archived']]);
        $this->assertSame($radios['here']->id, $lookup(' 867tzb 1042 ')->json('data.id'));
        $this->assertFalse($lookup('1047')->json('data.here'));
        $this->assertTrue($lookup('867TZB1046')->json('data.archived'));
        $lookup('99999')->assertNotFound()->assertJsonPath('message', 'Radio dengan Radio ID atau SN itu tidak ada di register.');

        // A scan is saved at once; again, it is changed.
        $this->scan($session, $radios['here'])->assertOk()->assertJsonPath('message', 'Radio 1042 · MDC 412 terverifikasi.')
            ->assertJsonPath('data.found', 1)->assertJsonPath('data.recent.0.radio', '1042 · MDC 412');
        $this->scan($session, $radios['here'], ['condition' => 'rusak', 'note' => 'Antena retak'])->assertOk();
        $this->assertSame([['rusak', 'Antena retak']], RadioOpnameItem::query()->get(['condition', 'note'])->map(fn ($item) => [$item->condition, $item->note])->all());
        $this->assertSame(['holder' => 'register', 'holder_name' => null, 'condition' => 'rusak', 'note' => 'Antena retak', 'ticket' => null],
            array_intersect_key($lookup('1042')->json('data.scanned'), array_flip(['condition', 'note', 'holder', 'holder_name', 'ticket'])));

        $this->scan($session, $radios['lab'], ['holder' => 'other'])->assertStatus(422)->assertJsonPath('errors.0', 'Tulis nama yang memegang radionya.');
        $this->scan($session, $radios['lab'], ['holder' => 'other', 'holder_name' => 'Peminjam Sementara'])->assertOk();
        $this->scan($session, $radios['archived'])->assertStatus(422)->assertJsonPath('errors.0', 'Radio ini sudah diarsipkan: tidak bisa diverifikasi. Pulihkan dulu di Register Radio.');
        $this->scan($session, $radios['here'], ['condition' => 'belum_dicek'])->assertStatus(422);

        // What needs a decision.
        $state = $this->getJson(route('radio-management.opname.show', $session))->assertOk()->json('data');
        $this->assertSame([2, 1, 2], [$state['expected'], $state['found'], $state['scanned']]);
        $this->assertSame(['1043 · MDC 413'], array_column($state['not_found'], 'radio'));
        $this->assertSame([['1047 · MDC 417', 'Pemakai Lab', 'Peminjam Sementara']], array_map(fn ($one) => [$one['radio'], $one['register'], $one['holder']], $state['other_user']));
        $this->assertSame([['1047 · MDC 417', 'Bagian Lab']], array_map(fn ($one) => [$one['radio'], $one['department']], $state['elsewhere']));
    }

    public function test_sending_the_session_verifies_what_was_found_and_writes_one_audit_line(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $radios = $this->radios();
        $this->actingAs($this->superAdmin());
        $session = $this->start()->json('data.session.id');
        $this->putJson(route('radio-management.opname.submit', $session))->assertStatus(422)->assertJsonPath('errors.0', 'Belum ada radio yang discan di sesi ini.');

        $this->scan($session, $radios['here'], ['condition' => 'rusak', 'note' => 'Antena retak'])->assertOk();
        $this->scan($session, $radios['lab'], ['holder' => 'other', 'holder_name' => 'Peminjam Sementara'])->assertOk();
        $this->putJson(route('radio-management.opname.submit', $session))->assertOk()
            ->assertJsonPath('message', '2 radio terverifikasi di register.')
            ->assertJsonPath('data.session.status', 'submitted');

        $here = $radios['here']->fresh();
        $this->assertSame(['2026-10-20', 'rusak'], [$here->last_verified_at->toDateString(), $here->condition->value]);
        $this->assertSame('Stock opname Bagian Pabrik: dipegang sesuai register. Kondisi Rusak. Antena retak',
            RadioHistory::query()->where('radio_id', $here->id)->where('action', 'Verifikasi')->value('description'));
        $this->assertSame('Stock opname Bagian Pabrik: dipegang Peminjam Sementara (register: Pemakai Lab). Kondisi Layak.',
            RadioHistory::query()->where('radio_id', $radios['lab']->id)->where('action', 'Verifikasi')->value('description'));
        $this->assertSame('2025-01-15', $radios['stock']->fresh()->last_verified_at->toDateString(), 'what was not found is left alone');

        $sent = RadioOpnameSession::query()->findOrFail($session);
        $this->assertSame(['submitted', 2, ['verified' => 2, 'not_found' => 1, 'other_user' => 1, 'skipped' => []]], [$sent->status, $sent->expected, $sent->summary]);
        $audit = AuditLog::query()->where('action', 'radio.opname_submitted')->sole();
        $this->assertSame(['RadioOpnameSession', $session, 'Stock opname Bagian Pabrik · 20-10-2026'], [$audit->entity_type, (int) $audit->entity_id, $audit->entity_label]);
        $this->assertSame(['Tempat' => 'Bagian Pabrik', 'Diharapkan' => '2', 'Terverifikasi' => '2', 'Tidak ditemukan' => '1', 'Pemegang lain' => '1'], $audit->new_values);

        // Sent is sent; the next one starts anew, and the last one is told.
        $this->putJson(route('radio-management.opname.submit', $session))->assertStatus(422)->assertJsonPath('errors.0', 'Sesi ini sudah dikirim ke register.');
        $this->scan($session, $radios['here'])->assertStatus(422)->assertJsonPath('errors.0', 'Sesi ini sudah dikirim ke register.');
        $this->getJson(route('radio-management.opname.outlook', ['department_id' => $this->plant->id]))
            ->assertJsonPath('data.last', ['date' => '2026-10-20', 'verified' => 2])->assertJsonPath('data.open', null);
        $this->assertNotSame($session, $this->start()->assertCreated()->json('data.session.id'));
    }

    public function test_a_problem_found_is_reported_as_a_service_ticket(): void
    {
        $radios = $this->radios();
        $this->actingAs($this->superAdmin());
        $session = $this->start()->json('data.session.id');

        $this->scan($session, $radios['here'], ['condition' => 'perlu_service'], 'radio-management.opname.report')->assertStatus(422)
            ->assertJsonPath('errors.0', 'Tulis masalahnya di catatan dulu.');
        $this->scan($session, $radios['here'], ['condition' => 'perlu_service', 'note' => 'Tombol PTT macet'], 'radio-management.opname.report')->assertOk()
            ->assertJsonPath('message', 'Tiket SRV-0001 dibuat untuk radio 1042 · MDC 412.');
        $ticket = ServiceTicket::query()->sole();
        $this->assertSame(['Tombol PTT macet', 'Stock opname · Bagian Pabrik', 'reported'], [$ticket->issue, $ticket->reported_by, $ticket->status]);
        $this->assertSame(['perlu_service', 'SRV-0001'], [RadioOpnameItem::query()->sole()->condition, RadioOpnameItem::query()->sole()->ticket_number]);

        // Without Create on Servis & Perbaikan: refused (scanning is still allowed).
        $this->actingAs($this->withOpname(['Read', 'Update'], 'penghitung@test.local'));
        $this->scan($session, $radios['stock'], ['note' => 'Layar retak'], 'radio-management.opname.report')->assertForbidden()
            ->assertJsonPath('errors.0', 'Melaporkan masalah butuh izin membuat tiket di Servis & Perbaikan.');
        $this->scan($session, $radios['stock'])->assertOk();
    }

    // ================================================================ who may do what, and around it

    public function test_reading_needs_the_menu_starting_needs_create_and_scanning_and_sending_need_update(): void
    {
        $radios = $this->radios();
        $this->actingAs($this->superAdmin());
        $session = $this->start()->json('data.session.id');

        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->getJson(route('radio-management.opname.show', $session))->assertOk();
        $this->start()->assertForbidden();
        $this->scan($session, $radios['here'])->assertForbidden();
        $this->putJson(route('radio-management.opname.submit', $session))->assertForbidden();

        // Update without Create: goes on with a session, does not start one.
        $this->actingAs($this->withOpname(['Read', 'Update'], 'penghitung@test.local'));
        $this->start()->assertForbidden();
        $this->scan($session, $radios['here'])->assertOk();

        RadioSectionMenuSeeder::remove(OpnameMenuSeeder::ROUTE);
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $this->get(route('radio-management.opname.index'))->assertForbidden();
        $this->actingAs($this->superAdmin());
        $this->get(route('radio-management.opname.index'))->assertOk();
    }

    public function test_an_import_that_starts_over_waits_for_the_sessions_still_open(): void
    {
        $this->radios();
        $this->actingAs($this->superAdmin());
        $this->start()->assertCreated();

        try {
            app(RadioImportService::class)->import(base_path('tidak-ada.xlsx'), ['fresh' => true]);
            $this->fail('A fresh import went ahead with a session open.');
        } catch (RuntimeException $refused) {
            $this->assertSame('Masih ada 1 sesi stock opname yang terbuka. Kirim sesi itu ke register dulu (menu Stock Opname), baru impor yang menghapus data lama bisa dijalankan.', $refused->getMessage());
        }
    }

    public function test_before_the_migration_has_run_the_page_says_so(): void
    {
        Schema::drop('radio_opname_items');
        Schema::drop('radio_opname_sessions');
        Cache::forget('radio-opname-tables');
        $this->actingAs($this->superAdmin());

        $this->assertStringContainsString(e(OpnameController::NOT_READY), (string) $this->get(route('radio-management.opname.index'))->assertOk()->getContent());
        $this->getJson(route('radio-management.opname.outlook', ['department_id' => $this->plant->id]))->assertStatus(503);
        $this->start()->assertStatus(503);
        $this->assertSame(0, RadioOpnameSession::openCount());
    }

    public function test_the_script_of_the_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('radio-management.opname.index'), 'tests/tools/opname-behaviour.mjs', 10);
    }
}
