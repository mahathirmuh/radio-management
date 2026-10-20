<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The Radio ID picker of the form (radio-management.radio.id-check): whether a typed ID can be used, and when not, why
 * (its MDC Code has an F, another radio holds it, or keeps it while it has none), who holds it, what to use instead,
 * the IDs around it and the next free ones.
 */
class RadioIdPickerTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    private function check(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->getJson(route('radio-management.radio.id-check', $query));
    }

    /** A user whose only role reads Register Radio. */
    private function reader(): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Baca Radio', 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $menuId = DB::table('menu')->where('route', 'radio-management.radio.index')->value('id');
        DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('menu_role_permission')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS['Read'], 'created_at' => now(), 'updated_at' => now()]);

        return $this->makeUser('baca.radio@test.local', (int) $roleId, 'Baca Radio');
    }

    public function test_an_id_with_f_is_refused_and_the_first_one_after_the_run_of_f_is_offered(): void
    {
        $this->actingAs($this->superAdmin());

        // 751 = 2EF; 752..767 = 2F0..2FF all have F; 768 = 300 is the first that has none.
        $data = $this->check(['id' => 751])->assertOk()->json('data');
        $this->assertSame(['id' => 751, 'mdc' => '2EF', 'status' => 'hex_f', 'own' => false, 'holder' => null], array_intersect_key($data, array_flip(['id', 'mdc', 'status', 'own', 'holder'])));
        $this->assertSame(['id' => 768, 'mdc' => '300'], $data['suggested']);
        $this->assertSame(['from' => 751, 'to' => 767], $data['skipped']);
        // The strip: the one below, this one, the next with F, how many more are skipped, the last with F, the suggestion.
        $this->assertSame([
            ['id' => 750, 'mdc' => '2EE', 'state' => 'free', 'selected' => false],
            ['id' => 751, 'mdc' => '2EF', 'state' => 'hex_f', 'selected' => true],
            ['id' => 752, 'mdc' => '2F0', 'state' => 'hex_f', 'selected' => false],
            ['gap' => 14],
            ['id' => 767, 'mdc' => '2FF', 'state' => 'hex_f', 'selected' => false],
            ['id' => 768, 'mdc' => '300', 'state' => 'free', 'selected' => false],
        ], $data['strip']);
        $this->assertSame([768, 769, 770, 771, 772], array_column($data['next'], 'id'));

        // Taken IDs past the run are passed over too.
        Radio::create(['dmr_id' => 768, 'serial_number' => '867TZB0768', 'user_name' => 'dipakai']);
        $this->assertSame(769, $this->check(['id' => 751])->json('data.suggested.id'));
    }

    public function test_an_id_that_another_radio_holds_or_keeps_says_who_and_what_to_use_instead(): void
    {
        $department = RadioDepartment::query()->value('id');
        $holder = Radio::create(['dmr_id' => 1042, 'serial_number' => '867TZB5309', 'user_name' => 'Pemakai Satu', 'department_id' => $department]);
        foreach ([1043, 1044, 1045, 1046] as $id) {
            Radio::create(['dmr_id' => $id, 'serial_number' => '867TZB' . $id, 'user_name' => 'lain']);
        }
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 900, 'serial_number' => '867TZB0900', 'user_name' => 'tanpa ID']);
        $this->actingAs($this->superAdmin());

        $taken = $this->check(['id' => 1042])->assertOk()->json('data');
        $this->assertSame('taken', $taken['status']);
        $this->assertSame($holder->id, $taken['holder']['id']);
        $this->assertStringStartsWith('ID 1042 · 867TZB5309', $taken['holder']['label']);
        $this->assertSame('Pemakai Satu', $taken['holder']['user']);
        $this->assertSame(RadioDepartment::find($department)?->name, $taken['holder']['department']);
        $this->assertSame(1047, $taken['suggested']['id']);
        // This one, the next held ones (shortened), the suggestion.
        $this->assertSame([1041, 1042, 1043, ['gap' => 2], 1046, 1047], array_map(fn ($cell) => $cell['id'] ?? ['gap' => $cell['gap']], $taken['strip']));
        $this->assertSame('used', $taken['strip'][1]['state']);

        // A radio without an ID still carries its last one: that one is kept for it.
        $reserved = $this->check(['id' => 900])->assertOk()->json('data');
        $this->assertSame('reserved', $reserved['status']);
        $this->assertSame('tanpa ID', $reserved['holder']['user']);
    }

    public function test_the_radio_being_edited_may_keep_its_own_id_and_an_archived_radio_holds_none(): void
    {
        $radio = Radio::create(['dmr_id' => 1200, 'serial_number' => '867TZB1200', 'user_name' => 'sendiri']);
        Radio::create(['dmr_id' => 1300, 'serial_number' => '867TZB1300', 'user_name' => 'diarsip', 'archived_at' => now()]);
        $imported = Radio::create(['dmr_id' => 751, 'serial_number' => '867TZB0751', 'user_name' => 'impor']);
        $this->actingAs($this->superAdmin());

        $own = $this->check(['id' => 1200, 'except' => $radio->id])->assertOk()->json('data');
        $this->assertSame(['ok', true, null, null], [$own['status'], $own['own'], $own['holder'], $own['suggested']]);
        $this->assertSame([1198, 1199, 1200, 1201, 1202, 1203], array_column($own['strip'], 'id'));
        $this->assertTrue($own['strip'][2]['selected']);
        $this->assertSame('taken', $this->check(['id' => 1200])->json('data.status'));

        // Archived: the ID is free again.
        $this->assertSame('ok', $this->check(['id' => 1300])->json('data.status'));

        // An imported ID with F: still refused, but said to be the radio's own (it may keep it until it is programmed again).
        $this->assertSame(['hex_f', true], [$this->check(['id' => 751, 'except' => $imported->id])->json('data.status'), $this->check(['id' => 751, 'except' => $imported->id])->json('data.own')]);
    }

    public function test_a_number_out_of_the_range_is_refused_and_the_menu_decides_who_may_ask(): void
    {
        $this->actingAs($this->superAdmin());
        foreach ([['id' => 0], ['id' => 'abc'], ['id' => 16776416], ['id' => ['1']], []] as $query) {
            $this->check($query)->assertStatus(422)->assertJsonPath('message', 'Radio ID harus angka 1 sampai 16776415.');
        }

        // Read on Register Radio is enough; without the menu, no.
        $this->actingAs($this->reader());
        $this->check(['id' => 1053])->assertOk()->assertJsonPath('data.status', 'ok');
        $this->actingAs($this->makeUser('tanpa.menu@test.local'));
        $this->check(['id' => 1053])->assertForbidden();
    }

    public function test_the_script_of_the_picker_behaves_as_described(): void
    {
        $this->checkPageScript(route('radio-management.radio.index'), 'tests/tools/radio-id-picker-behaviour.mjs', 5);
    }
}
