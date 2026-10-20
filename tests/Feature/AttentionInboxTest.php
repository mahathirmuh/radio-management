<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use Database\Seeders\AttentionMenuSeeder;
use Database\Seeders\RadioSectionMenuSeeder;
use Database\Seeders\ServiceMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RadioManagement\Http\Controllers\AttentionController;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCheckState;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\AttentionInbox;
use Modules\RadioManagement\Services\RadioIdService;
use Modules\RadioManagement\Services\RadioService;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * "Perlu Perhatian" (6.4): the register's checks as items. Each check offers its fixes, applied through RadioService;
 * an item is set aside with a reason until its radios change, and given to somebody who may change radios.
 */
class AttentionInboxTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    private int $next = 0;

    /** A radio without any problem, unless $other gives it one. */
    private function radio(array $other = []): Radio
    {
        $this->next++;
        $type = RadioType::query()->firstOrCreate(['name' => 'XiR P8668i'], ['category' => 'HT', 'is_active' => true]);
        // (IDs from 0x1001 up: none with F in it, which would be a check of its own.)
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

    private function list(string $check, array $other = []): array
    {
        return $this->getJson(route('radio-management.attention.list', ['check' => $check] + $other))->assertOk()->json();
    }

    private function show(string $check, string $subject): array
    {
        return $this->getJson(route('radio-management.attention.show', ['check' => $check, 'subject' => $subject]))->assertOk()->json('data');
    }

    private function fix(string $check, string $subject, string $option, ?string $value = null)
    {
        return $this->putJson(route('radio-management.attention.fix'), array_filter(['check' => $check, 'subject' => $subject, 'option' => $option, 'value' => $value], fn ($v) => $v !== null));
    }

    private function counts(): array
    {
        return array_filter($this->list('needs_review')['counts']);
    }

    /** A user whose only role has these permissions on Perlu Perhatian, and these on Register Radio. */
    private function withMenus(array $attention, array $register, string $email): User
    {
        $roleId = (int) DB::table('roles')->insertGetId(['name' => 'Peran ' . $email, 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([AttentionMenuSeeder::ROUTE => $attention, 'radio-management.radio.index' => $register] as $route => $permissions) {
            $menuId = DB::table('menu')->where('route', $route)->value('id');
            DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($permissions as $permission) {
                DB::table('menu_role_permission')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS[$permission], 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Cache::forget('rbac_menu_route_key_map');

        return $this->makeUser($email, $roleId, 'Petugas ' . $this->next++);
    }

    // ================================================================ the menu

    public function test_the_menu_is_right_below_register_radio_with_the_open_items_in_red(): void
    {
        $register = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('order');
        $places = DB::table('menu')->whereNull('parent_id')->where('order', '>=', $register)->orderBy('order')->limit(4)
            ->pluck('order', 'title')->map(fn ($place) => (int) $place)->all();
        $this->assertSame(['Register Radio' => $register, 'Perlu Perhatian' => $register + 1, 'Servis & Perbaikan' => $register + 2, 'Stock Opname' => $register + 3], $places);
        $menu = DB::table('menu')->where('route', AttentionMenuSeeder::ROUTE)->sole();
        $this->assertSame(['Perlu Perhatian', 'alert-triangle'], [$menu->title, $menu->icon]);
        $grants = fn (string $route) => DB::table('menu_role_permission')->where('menu_id', DB::table('menu')->where('route', $route)->value('id'))
            ->orderBy('role_id')->orderBy('permission_id')->get()->map(fn ($grant) => [(int) $grant->role_id, (int) $grant->permission_id])->all();
        $this->assertSame($grants('radio-management.radio.index'), $grants(AttentionMenuSeeder::ROUTE));

        // The number of open items, red, on the entry.
        $this->radio(['last_verified_at' => null]);
        $this->radio(['serial_number' => null]);
        $this->actingAs($this->superAdmin());
        Cache::forget(AttentionInbox::COUNT_CACHE);
        $page = (string) $this->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#' . preg_quote(route('radio-management.attention.index'), '#') . '".*?Perlu Perhatian</span>\s*<span class="rm-badge red rm-nav-count" title="2 item perlu perhatian">2</span>#s', $page);
        // ... and "Buka inbox" on the dashboard.
        $this->assertStringContainsString('href="' . route('radio-management.attention.index') . '" class="rm-cardbox-link">Buka inbox', $page);

        // The migration takes it away, closing the gap, and puts it back: Servis & Perbaikan stays below it.
        $migration = require database_path('migrations/2026_10_20_130000_add_attention_menu.php');
        $migration->down();
        $this->assertSame(['Register Radio', 'Servis & Perbaikan'], DB::table('menu')->whereNull('parent_id')->whereIn('order', [$register, $register + 1])->orderBy('order')->pluck('title')->all());
        $migration->up();
        $this->assertSame(['Register Radio', 'Perlu Perhatian', 'Servis & Perbaikan'], DB::table('menu')->whereNull('parent_id')->whereIn('order', [$register, $register + 1, $register + 2])->orderBy('order')->pluck('title')->all());
        $this->assertSame($grants('radio-management.radio.index'), $grants(AttentionMenuSeeder::ROUTE));
    }

    // ================================================================ the items

    public function test_radios_that_share_an_sn_are_one_item_compared_side_by_side_and_fixed(): void
    {
        $old = $this->radio(['dmr_id' => 2001, 'serial_number' => '867TZB5309', 'user_name' => 'Pemakai Satu']);
        $new = $this->radio(['dmr_id' => 2002, 'serial_number' => '867TZB5309', 'user_name' => 'Pemakai Dua']);
        $this->radio(['last_verified_at' => null]);
        $this->actingAs($this->superAdmin());

        $list = $this->list('sn_duplicate');
        $this->assertSame(['sn_duplicate' => 1, 'verify_empty' => 1], array_filter($list['counts']));
        $this->assertSame([['867TZB5309', '867TZB5309', 'Radio 2001 dan 2002', 'Pemakai Satu dan Pemakai Dua', null, now()->toDateString()]],
            array_map(fn ($item) => [$item['subject'], $item['head'], $item['side'], $item['who'], $item['assignee'], $item['first_seen']], $list['data']));
        $this->assertSame(1, RadioCheckState::query()->where('check_code', 'sn_duplicate')->where('subject', '867TZB5309')->count(), 'first seen');

        $item = $this->show('sn_duplicate', '867TZB5309');
        $this->assertSame(['SN 867TZB5309 dipakai 2 radio', 'SN dobel', 'red'], [$item['title'], $item['label'], $item['color']]);
        $this->assertSame([[false, 'Radio 2001'], [true, 'Radio 2002']], array_map(fn ($record) => [$record['suspect'], $record['head']], $item['records']));
        $marked = fn (array $record) => array_column(array_filter($record['rows'], fn ($row) => $row['marked']), 'label');
        $this->assertSame(['Radio ID', 'User/Unit'], $marked($item['records'][1]), 'what differs');
        $this->assertSame(["sn:{$new->id}", "archive:{$new->id}", 'dismiss'], array_column($item['options'], 'id'));

        // The SN another radio has is refused; a free one, typed carelessly, is taken in its stored form.
        $this->fix('sn_duplicate', '867TZB5309', "sn:{$new->id}", '867TZB4099')->assertStatus(422)
            ->assertJsonPath('errors.0', fn ($message) => str_contains($message, 'SN 867TZB4099 sudah terdaftar'));
        $this->fix('sn_duplicate', '867TZB5309', "sn:{$old->id}", '867TZB5399')->assertStatus(422)->assertJsonPath('errors.0', 'Perbaikan itu tidak ada untuk item ini. Muat ulang.');
        $this->fix('sn_duplicate', '867TZB5309', "sn:{$new->id}", ' 867tzb 5311 ')->assertOk()->assertJsonPath('message', 'SN radio ID 2002 · 867TZB5309 sekarang 867TZB5311.');
        $this->assertSame('867TZB5311', $new->fresh()->serial_number);
        $this->assertSame('SN: 867TZB5309 → 867TZB5311', RadioHistory::query()->where('radio_id', $new->id)->latest('id')->value('description'));
        $this->assertSame(['SN' => '867TZB5309'], AuditLog::query()->where('action', 'radio.updated')->latest('id')->first()->old_values);

        // Fixed, it is gone, with what was known of it.
        $this->assertSame([], $this->list('sn_duplicate')['data']);
        $this->assertSame(0, RadioCheckState::query()->where('check_code', 'sn_duplicate')->count());
        $this->fix('sn_duplicate', '867TZB5309', "sn:{$new->id}", '867TZB5312')->assertStatus(422)
            ->assertJsonPath('errors.0', 'Item ini sudah tidak ada: datanya sudah dibetulkan atau berubah. Muat ulang.');
        $this->getJson(route('radio-management.attention.show', ['check' => 'sn_duplicate', 'subject' => '867TZB5309']))->assertNotFound();
    }

    public function test_every_check_offers_its_fixes_and_applies_them_through_the_register(): void
    {
        $this->withoutSessionTimeouts();
        $type = RadioType::query()->firstOrCreate(['name' => 'XiR P6620i'], ['category' => 'HT', 'is_active' => true]);
        $template = ChannelTemplate::create(['name' => 'Template Contoh', 'channels' => 'CH1', 'effective_date' => now()->subDays(10)->toDateString(), 'is_active' => true]);
        $twinA = $this->radio(['dmr_id' => 2101]);
        $twinB = $this->radio(['dmr_id' => 2101]);
        $hexF = $this->radio(['dmr_id' => 751]);
        $badSn = $this->radio(['serial_number' => '867-TZB-5312']);
        $noSn = $this->radio(['serial_number' => null]);
        $noId = $this->radio(['dmr_id' => null, 'previous_dmr_id' => 2201]);
        $noType = $this->radio(['radio_type_id' => null]);
        $lost = $this->radio(['placement' => 'hilang', 'document_no' => null]);
        $lostToo = $this->radio(['placement' => 'hilang', 'document_no' => null]);
        $old = $this->radio(['channel_template_id' => $template->id, 'last_programmed_at' => null]);
        $stale = $this->radio(['last_verified_at' => now()->subYears(2)->toDateString()]);
        $review = $this->radio(['needs_review' => true, 'review_notes' => 'Dicek ulang <b>dulu</b>']);
        $this->actingAs($this->superAdmin());

        $options = fn (string $check, $subject) => array_column($this->show($check, (string) $subject)['options'], 'title', 'id');
        $next = app(RadioIdService::class)->nextFree(1)[0];

        // Radio ID twice: the newer radio gets the lowest free ID (or is archived).
        $this->assertSame(["next_id:{$twinB->id}", "archive:{$twinB->id}"], array_keys($options('id_duplicate', 2101)));
        $this->fix('id_duplicate', '2101', "next_id:{$twinB->id}")->assertOk();
        $this->assertSame([2101, $next], [$twinA->fresh()->dmr_id, $twinB->fresh()->dmr_id]);

        // An ID with F: the first one above it that can be used.
        $this->assertSame(["next_id:{$hexF->id}" => 'Pakai ID berikutnya yang bisa dipakai, 768 (MDC 300)'], $options('id_hex_f', $hexF->id));
        $this->fix('id_hex_f', (string) $hexF->id, "next_id:{$hexF->id}")->assertOk()->assertJsonPath('message', fn ($message) => str_contains($message, 'Radio ID 768 (MDC 300)'));
        $this->assertSame(768, $hexF->fresh()->dmr_id);

        // An SN out of form comes with the form suggested; an empty one is typed.
        $this->assertSame('867TZB5312', $this->show('sn_format', (string) $badSn->id)['options'][0]['field']['value']);
        $this->fix('sn_format', (string) $badSn->id, "sn:{$badSn->id}", '867TZB5312')->assertOk();
        $this->fix('sn_empty', (string) $noSn->id, "sn:{$noSn->id}", '')->assertStatus(422)->assertJsonPath('errors.0', 'Ketik SN yang benar.');
        $this->fix('sn_empty', (string) $noSn->id, "sn:{$noSn->id}", '867TZB5313')->assertOk();
        $this->assertSame(['867TZB5312', '867TZB5313'], [$badSn->fresh()->serial_number, $noSn->fresh()->serial_number]);

        // No Radio ID: its last one back, while it is free.
        $this->assertSame(["previous_id:{$noId->id}", "next_id:{$noId->id}"], array_keys($options('id_empty', $noId->id)));
        $this->fix('id_empty', (string) $noId->id, "previous_id:{$noId->id}")->assertOk();
        $this->assertSame(2201, $noId->fresh()->dmr_id);

        // A type from the master.
        $this->fix('type_empty', (string) $noType->id, "type:{$noType->id}", '999999')->assertStatus(422)->assertJsonPath('errors.0', 'Pilih tipe radio.');
        $this->fix('type_empty', (string) $noType->id, "type:{$noType->id}", (string) $type->id)->assertOk();
        $this->assertSame($type->id, (int) $noType->fresh()->radio_type_id);

        // Lost: the BA, and the radio stays; or the BA and it is archived as lost.
        $this->fix('lost_no_ba', (string) $lost->id, "ba:{$lost->id}", 'BA-HILANG-01')->assertOk();
        $this->assertSame(['BA-HILANG-01', null], [$lost->fresh()->document_no, $lost->fresh()->archived_at]);
        $this->fix('lost_no_ba', (string) $lostToo->id, "ba_archive:{$lostToo->id}", 'BA-HILANG-02')->assertOk();
        $this->assertSame(['BA-HILANG-02', 'Hilang (BA selesai)'], [$lostToo->fresh()->document_no, $lostToo->fresh()->archive_reason]);

        // Programmed and verified today: a line in the history, the date of the radio moves on.
        $this->fix('reprogram', (string) $old->id, "program:{$old->id}")->assertOk();
        $this->fix('verify_stale', (string) $stale->id, "verify:{$stale->id}")->assertOk();
        $this->assertSame(now()->toDateString(), $old->fresh()->last_programmed_at->toDateString());
        $this->assertSame(now()->toDateString(), $stale->fresh()->last_verified_at->toDateString());
        $this->assertSame(['Program', 'Diprogram ulang ke template Template Contoh (Perlu Perhatian).'],
            [RadioHistory::query()->where('radio_id', $old->id)->latest('id')->value('action'), RadioHistory::query()->where('radio_id', $old->id)->latest('id')->value('description')]);

        // Reviewed: the mark goes.
        $this->assertSame('Dicek ulang <b>dulu</b>', $this->show('needs_review', (string) $review->id)['note']);
        $this->fix('needs_review', (string) $review->id, "reviewed:{$review->id}")->assertOk();
        $this->assertFalse($review->fresh()->needs_review);

        // Nothing is left.
        $this->assertSame([], $this->counts());
        $this->fix('needs_review', (string) $review->id, 'dismiss')->assertStatus(422);
    }

    public function test_an_item_set_aside_needs_a_reason_and_is_back_when_its_radio_changes(): void
    {
        $radio = $this->radio(['last_verified_at' => null]);
        $this->actingAs($this->superAdmin());
        $this->assertSame(['verify_empty' => 1], $this->counts());
        Cache::put(AttentionInbox::COUNT_CACHE, 1, 60);

        $this->putJson(route('radio-management.attention.dismiss'), ['check' => 'verify_empty', 'subject' => (string) $radio->id, 'reason' => ' '])
            ->assertStatus(422)->assertJsonPath('errors.0', 'Tulis alasan item ini diabaikan.');
        $this->putJson(route('radio-management.attention.dismiss'), ['check' => 'verify_empty', 'subject' => (string) $radio->id, 'reason' => 'Radio cadangan di gudang'])
            ->assertOk();
        $this->assertNull(Cache::get(AttentionInbox::COUNT_CACHE), 'the number of the sidebar is worked out again');
        $this->assertSame([], $this->counts());
        $this->assertSame([], $this->list('verify_empty')['data']);
        $audit = AuditLog::query()->where('action', 'attention.dismissed')->sole();
        $this->assertSame(['Radio', $radio->id, 'Tgl verifikasi kosong: ' . $radio->display_name, ['Alasan' => 'Radio cadangan di gudang']],
            [$audit->entity_type, (int) $audit->entity_id, $audit->entity_label, $audit->new_values]);
        // Asked for by name, it says why it is aside.
        $this->assertSame('Radio cadangan di gudang', $this->show('verify_empty', (string) $radio->id)['dismissed']['reason']);

        // The radio changes: the item is back.
        $this->travel(1)->seconds();
        app(RadioService::class)->update($radio, ['user_name' => 'Pemakai Baru'], null);
        $this->assertSame(['verify_empty' => 1], $this->counts());
        $this->assertSame([(string) $radio->id], array_column($this->list('verify_empty')['data'], 'subject'));
    }

    public function test_an_item_is_given_to_somebody_who_may_change_radios(): void
    {
        $radio = $this->radio(['serial_number' => null]);
        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio', 'Admin Contoh');
        $viewer = $this->makeUser('viewer@test.local', 'Viewer Radio', 'Viewer Contoh');
        $boss = $this->superAdmin();
        $this->actingAs($boss);
        $subject = (string) $radio->id;

        $people = app(AttentionInbox::class)->assignable();
        $this->assertContains(['id' => $admin->id, 'name' => 'Admin Contoh'], $people);
        $this->assertNotContains(['id' => $viewer->id, 'name' => 'Viewer Contoh'], $people);

        $assign = fn ($userId) => $this->putJson(route('radio-management.attention.assign'), ['check' => 'sn_empty', 'subject' => $subject, 'user_id' => $userId]);
        $assign($viewer->id)->assertStatus(422)->assertJsonPath('errors.0', 'Item hanya bisa ditugaskan ke akun aktif yang boleh mengubah data radio.');
        $assign($admin->id)->assertOk()->assertJsonPath('message', 'Item ditugaskan ke Admin Contoh.');
        $this->assertSame(['id' => $admin->id, 'name' => 'Admin Contoh'], $this->list('sn_empty')['data'][0]['assignee']);
        $this->assertSame([], $this->list('sn_empty', ['who' => 'mine'])['data']);
        $this->assertSame([], $this->list('sn_empty', ['who' => 'none'])['data']);
        $this->assertSame(['Ditugaskan ke' => 'Admin Contoh'], AuditLog::query()->where('action', 'attention.assigned')->sole()->new_values);

        $this->actingAs($admin);
        $this->assertSame([$subject], array_column($this->list('sn_empty', ['who' => 'mine'])['data'], 'subject'));
        $assign('')->assertOk()->assertJsonPath('message', 'Item tidak lagi ditugaskan ke siapa pun.');
        $this->assertSame([$subject], array_column($this->list('sn_empty', ['who' => 'none'])['data'], 'subject'));
    }

    // ================================================================ who may do what

    public function test_reading_needs_the_menu_changing_needs_update_and_a_fix_needs_the_register_too(): void
    {
        $radio = $this->radio(['last_verified_at' => null]);
        $item = ['check' => 'verify_empty', 'subject' => (string) $radio->id];

        // Viewer Radio reads.
        $this->actingAs($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->get(route('radio-management.attention.index'))->assertOk()->assertViewHas('may', ['update' => false, 'fix' => false]);
        $this->assertSame(1, $this->list('verify_empty')['total']);
        $this->putJson(route('radio-management.attention.dismiss'), $item + ['reason' => 'Coba'])->assertForbidden();
        $this->putJson(route('radio-management.attention.assign'), $item + ['user_id' => null])->assertForbidden();
        $this->putJson(route('radio-management.attention.fix'), $item + ['option' => "verify:{$radio->id}"])->assertForbidden();

        // Update here, only Read on the register: sets aside, does not fix.
        $this->actingAs($this->withMenus(['Read', 'Update'], ['Read'], 'pengecek@test.local'));
        $this->get(route('radio-management.attention.index'))->assertOk()->assertViewHas('may', ['update' => true, 'fix' => false]);
        $this->putJson(route('radio-management.attention.fix'), $item + ['option' => "verify:{$radio->id}"])->assertForbidden()
            ->assertJsonPath('errors.0', 'Memperbaiki data radio butuh izin Update di Register Radio.');
        $this->putJson(route('radio-management.attention.dismiss'), $item + ['reason' => 'Cadangan'])->assertOk();

        // Without its menu row: closed to everyone but Super Admin.
        RadioSectionMenuSeeder::remove(AttentionMenuSeeder::ROUTE);
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $this->get(route('radio-management.attention.index'))->assertForbidden();
        $this->actingAs($this->superAdmin());
        $this->get(route('radio-management.attention.index'))->assertOk();
        // (The pages below it moved up again.)
        $register = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('order');
        $this->assertSame($register + 1, (int) DB::table('menu')->where('route', ServiceMenuSeeder::ROUTE)->value('order'));
    }

    public function test_before_the_migration_has_run_the_checks_are_shown_without_states(): void
    {
        $radio = $this->radio(['last_verified_at' => null]);
        Schema::drop('radio_check_states');
        Cache::forget('radio-check-states-table');
        $this->actingAs($this->superAdmin());

        $page = (string) $this->get(route('radio-management.attention.index'))->assertOk()->getContent();
        $this->assertStringContainsString(e(AttentionController::NOT_READY), $page);
        $list = $this->list('verify_empty');
        $this->assertSame([[(string) $radio->id, null]], array_map(fn ($item) => [$item['subject'], $item['first_seen']], $list['data']));
        $this->putJson(route('radio-management.attention.dismiss'), ['check' => 'verify_empty', 'subject' => (string) $radio->id, 'reason' => 'x'])->assertStatus(503);
        $this->putJson(route('radio-management.attention.assign'), ['check' => 'verify_empty', 'subject' => (string) $radio->id, 'user_id' => null])->assertStatus(503);
        // A fix needs no state.
        $this->fix('verify_empty', (string) $radio->id, "verify:{$radio->id}")->assertOk();
        $this->assertSame([], $this->counts());
    }

    public function test_the_script_of_the_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('radio-management.attention.index'), 'tests/tools/attention-inbox-behaviour.mjs', 10);
    }
}
