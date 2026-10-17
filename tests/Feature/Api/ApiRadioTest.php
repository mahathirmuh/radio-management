<?php

namespace Tests\Feature\Api;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioIdService;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * The register through the API: the same rules, history and audit trail as the pages.
 */
class ApiRadioTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    private function radio(array $overrides = []): array
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

    public function test_a_radio_is_registered_changed_and_read_back_with_the_rules_of_the_form(): void
    {
        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio', 'Andi Admin');
        $token = $this->tokenFor($admin, name: 'Inventaris');

        $made = $this->api('POST', '/api/v1/radios', $this->radio(), $token)->assertCreated()->json('data');
        $this->assertSame([2001, '7D1', '867TZB9999', 'Budi Santoso', 'di_user', 'Di User', false], [
            $made['dmr_id'], $made['mdc_code'], $made['serial_number'], $made['user_name'], $made['placement']['value'], $made['placement']['label'], $made['archived'],
        ]);
        $this->assertSame('MotoTrbo XiR P6620i', $made['radio_type']['name']);
        $this->assertNotEmpty($made['version']);
        $radio = Radio::findOrFail($made['id']);

        // The rules of the form, with the messages by field.
        $this->api('POST', '/api/v1/radios', $this->radio(['serial_number' => '867TZB0002']), $token)
            ->assertStatus(422)->assertJsonValidationErrors(['dmr_id']);
        $this->api('POST', '/api/v1/radios', $this->radio(['dmr_id' => 15, 'serial_number' => '867TZB0003']), $token)
            ->assertStatus(422)->assertJsonValidationErrors(['dmr_id']);
        $this->api('POST', '/api/v1/radios', $this->radio(['dmr_id' => 2002]), $token)
            ->assertStatus(422)->assertJsonValidationErrors(['serial_number']);
        $this->api('POST', '/api/v1/radios', ['serial_number' => '867TZB0004'], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['radio_type_id', 'placement', 'condition']);

        // A change based on the version that was read goes through; one based on an older one is 409, and saves nothing.
        $old = $made['version'];
        $changed = $this->api('PUT', "/api/v1/radios/{$radio->id}", ['user_name' => 'Andi Wijaya', 'placement' => 'di_user', 'condition' => 'layak', 'version' => $old], $token)
            ->assertOk()->json('data');
        $this->assertSame('Andi Wijaya', $changed['user_name']);
        $this->api('PUT', "/api/v1/radios/{$radio->id}", ['user_name' => 'Citra', 'placement' => 'di_user', 'condition' => 'layak', 'version' => $old], $token)
            ->assertStatus(409)->assertJsonStructure(['message', 'errors' => ['radio']]);
        $this->assertSame('Andi Wijaya', $radio->fresh()->user_name);

        $this->api('GET', "/api/v1/radios/{$radio->id}", token: $token)->assertOk()->assertJsonPath('data.user_name', 'Andi Wijaya');

        // Into the history as the owner of the token, and into the audit trail with the token.
        $handover = $radio->histories()->first();
        $this->assertSame(['Serah Terima', (int) $admin->id], [$handover->action, (int) $handover->user_id]);
        $audit = AuditLog::where('action', AuditLogger::RADIO_UPDATED)->sole();
        $this->assertSame([(int) $admin->id, ['User/Unit' => 'Andi Wijaya']], [(int) $audit->user_id, $audit->new_values]);
        $this->assertMatchesRegularExpression('/^API · Inventaris \(#\d+\)$/', (string) $audit->via);
        $this->assertSame($audit->via, AuditLog::where('action', AuditLogger::RADIO_CREATED)->sole()->via);
        // What the pages write has no "via".
        $this->actingAs($admin);
        $this->putJson(route('radio-management.radio.update', $radio), ['user_name' => 'Dewi', 'placement' => 'di_user', 'condition' => 'layak'])->assertOk();
        $this->assertNull(AuditLog::where('action', AuditLogger::RADIO_UPDATED)->orderByDesc('id')->first()->via);
    }

    public function test_a_radio_is_archived_restored_given_history_and_deleted(): void
    {
        $token = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $id = $this->api('POST', '/api/v1/radios', $this->radio(), $token)->assertCreated()->json('data.id');

        $this->api('POST', "/api/v1/radios/{$id}/archive", ['archive_reason' => 'Bukan alasan'], $token)->assertStatus(422)
            ->assertJsonValidationErrors(['archive_reason', 'archived_at']);
        $archived = $this->api('POST', "/api/v1/radios/{$id}/archive", ['archive_reason' => 'Pindah Site', 'archived_at' => now()->toDateString()], $token)
            ->assertOk()->json('data');
        $this->assertSame([true, null, 2001], [$archived['archived'], $archived['dmr_id'], $archived['previous_dmr_id']]);
        $this->api('POST', "/api/v1/radios/{$id}/archive", ['archive_reason' => 'Pindah Site', 'archived_at' => now()->toDateString()], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['radio']);

        $this->api('POST', "/api/v1/radios/{$id}/restore", token: $token)->assertOk()->assertJsonPath('data.dmr_id', 2001);

        $line = $this->api('POST', "/api/v1/radios/{$id}/history", ['action' => 'Program', 'event_date' => now()->toDateString(), 'description' => 'Channel baru'], $token)
            ->assertCreated()->json('data');
        $this->assertSame(['Program', 'Channel baru'], [$line['action'], $line['description']]);
        $history = $this->api('GET', "/api/v1/radios/{$id}/history", token: $token)->assertOk()->json('data');
        $this->assertSame('Program', $history[0]['action']);
        $this->assertContains('Arsip', array_column($history, 'action'));

        $this->api('DELETE', "/api/v1/radios/{$id}", token: $token)->assertNoContent();
        $this->assertSoftDeleted('radios', ['id' => $id]);
        $this->api('GET', "/api/v1/radios/{$id}", token: $token)->assertNotFound();
    }

    public function test_archiving_and_adding_history_are_an_update_as_on_the_page(): void
    {
        $radio = Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB5309', 'user_name' => 'Iqbal']);
        // A role that may add radios and read them, but not change them.
        $roleId = DB::table('roles')->insertGetId(['name' => 'Penambah', 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $menu = (int) DB::table('menu')->where('route', 'radio-management.radio.index')->value('id');
        DB::table('menu_role')->insert(['menu_id' => $menu, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2] as $permission) {
            DB::table('menu_role_permission')->insert(['menu_id' => $menu, 'role_id' => $roleId, 'permission_id' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
        $token = $this->tokenFor($this->makeUser('penambah@test.local', $roleId));

        $this->api('POST', "/api/v1/radios/{$radio->id}/archive", ['archive_reason' => 'Pindah Site', 'archived_at' => now()->toDateString()], $token)->assertForbidden();
        $this->api('POST', "/api/v1/radios/{$radio->id}/history", ['action' => 'Service', 'event_date' => now()->toDateString(), 'description' => 'x'], $token)->assertForbidden();
        $this->api('POST', '/api/v1/radios', $this->radio(), $token)->assertCreated();
    }

    public function test_the_list_filters_and_pages_as_the_register_does(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $type = RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id');
        $department = RadioDepartment::firstOrCreate(['name' => 'MAINTENANCE'])->id;
        foreach (range(1, 7) as $i) {
            Radio::create(['dmr_id' => 100 + $i, 'serial_number' => sprintf('867TZB%04d', $i), 'user_name' => "User {$i}", 'radio_type_id' => $type,
                'department_id' => $i <= 3 ? $department : null, 'placement' => $i === 7 ? 'stok_it' : 'di_user']);
        }
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 200, 'serial_number' => '867TZB0200', 'user_name' => 'Gone', 'archived_at' => now()]);

        $page = $this->api('GET', '/api/v1/radios?per_page=5&sort=dmr_id&direction=desc', token: $token)->assertOk()->json();
        $this->assertSame([107, 106, 105, 104, 103], array_column($page['data'], 'dmr_id'));
        $this->assertSame([7, 2, 5], [$page['meta']['total'], $page['meta']['last_page'], $page['meta']['per_page']]);
        $this->assertArrayHasKey('next', $page['links']);

        $this->assertSame(3, $this->api('GET', "/api/v1/radios?department={$department}", token: $token)->json('meta.total'));
        $this->assertSame(3, $this->api('GET', "/api/v1/radios?department[]={$department}", token: $token)->json('meta.total'));
        $this->assertSame([107], array_column($this->api('GET', '/api/v1/radios?placement=stok_it', token: $token)->json('data'), 'dmr_id'));
        $this->assertSame(['867TZB0003'], array_column($this->api('GET', '/api/v1/radios?search=0003', token: $token)->json('data'), 'serial_number'));
        $this->assertSame(['867TZB0200'], array_column($this->api('GET', '/api/v1/radios?scope=archived', token: $token)->json('data'), 'serial_number'));
        $this->assertSame(8, $this->api('GET', '/api/v1/radios?scope=all', token: $token)->json('meta.total'));

        // What is no filter is said so.
        $this->api('GET', '/api/v1/radios?scope=everything&placement=di_kantor&per_page=500', token: $token)
            ->assertStatus(422)->assertJsonValidationErrors(['scope', 'placement.0', 'per_page']);
    }

    public function test_free_ids_and_the_check_of_an_id_and_an_sn(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'));
        Radio::create(['dmr_id' => 1, 'serial_number' => '867TZB0001', 'user_name' => 'x']);

        // The same IDs as the form offers.
        $free = $this->api('GET', '/api/v1/radios/free-ids?count=3', token: $token)->assertOk()->json('data');
        $this->assertSame(app(RadioIdService::class)->nextFree(3), array_column($free, 'dmr_id'));
        $this->assertSame(RadioIdService::mdc($free[0]['dmr_id']), $free[0]['mdc_code']);
        // Hex F is skipped: after 14 comes 16.
        $this->assertSame([16], array_column($this->api('GET', '/api/v1/radios/free-ids?count=1&after=14', token: $token)->json('data'), 'dmr_id'));

        $check = $this->api('GET', '/api/v1/radios/check?dmr_id=1&serial_number=867tzb0001', token: $token)->assertOk()->json('data');
        $this->assertFalse($check['dmr_id']['free']);
        $this->assertFalse($check['serial_number']['free']);
        $this->assertSame('867TZB0001', $check['serial_number']['value']);
        $this->assertNotEmpty($check['dmr_id']['message']);
        $this->assertTrue($this->api('GET', '/api/v1/radios/check?dmr_id=2', token: $token)->json('data.dmr_id.free'));
        $this->assertFalse($this->api('GET', '/api/v1/radios/check?dmr_id=15', token: $token)->json('data.dmr_id.free'));
    }

    public function test_the_history_of_all_radios(): void
    {
        $token = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $id = $this->api('POST', '/api/v1/radios', $this->radio(), $token)->json('data.id');
        $this->api('POST', "/api/v1/radios/{$id}/history", ['action' => 'Service', 'event_date' => now()->toDateString(), 'description' => 'Ganti antena', 'document_no' => 'WO-9'], $token)->assertCreated();

        $all = $this->api('GET', '/api/v1/history', token: $token)->assertOk()->json();
        $this->assertSame(['Service', 'Dibuat'], array_column($all['data'], 'action'));
        $this->assertSame('ID 2001 · 867TZB9999', $all['data'][0]['radio']['display_name']);
        $this->assertSame(['Service'], array_column($this->api('GET', '/api/v1/history?action=Service', token: $token)->json('data'), 'action'));
        $this->assertSame(['Service'], array_column($this->api('GET', '/api/v1/history?search=WO-9', token: $token)->json('data'), 'action'));
    }

    public function test_master_data_through_the_api(): void
    {
        $token = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'));

        $made = $this->api('POST', '/api/v1/radio-types', ['name' => 'Hytera PD-1', 'category' => 'HT', 'power' => '4 W', 'is_active' => true], $token)
            ->assertCreated()->json('data');
        $this->assertSame(['Hytera PD-1', 'HT', '4 W', true, 0], [$made['name'], $made['category'], $made['power'], $made['is_active'], $made['usage']]);
        $this->api('POST', '/api/v1/radio-types', ['name' => 'Hytera PD-1', 'category' => 'HT'], $token)->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->api('POST', '/api/v1/companies', ['code' => ' abc '], $token)->assertCreated()->assertJsonPath('data.code', 'ABC');

        $this->api('PUT', "/api/v1/radio-types/{$made['id']}", ['name' => 'Hytera PD-1', 'category' => 'HT', 'power' => '5 W', 'is_active' => true], $token)
            ->assertOk()->assertJsonPath('data.power', '5 W');
        $list = $this->api('GET', '/api/v1/radio-types?search=Hytera', token: $token)->assertOk()->json();
        $this->assertSame(['Hytera PD-1'], array_column($list['data'], 'name'));

        // In use: refused, switch it off instead.
        Radio::create(['dmr_id' => 11, 'serial_number' => 'A1', 'user_name' => 'x', 'radio_type_id' => $made['id']]);
        $this->api('DELETE', "/api/v1/radio-types/{$made['id']}", token: $token)->assertStatus(422);
        Radio::query()->forceDelete();
        $this->api('DELETE', "/api/v1/radio-types/{$made['id']}", token: $token)->assertNoContent();

        $this->assertSame(['master_data.created', 'master_data.created', 'master_data.updated', 'master_data.deleted'],
            AuditLog::where('action', 'like', 'master_data.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_references_and_the_dashboard(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'), write: false);
        Radio::create(['dmr_id' => 11, 'serial_number' => '867TZB5309', 'user_name' => 'x']);

        $references = $this->api('GET', '/api/v1/references', token: $token)->assertOk()->json('data');
        $this->assertSame(['di_user', 'stok_it', 'service', 'hilang'], array_column($references['placements'], 'value'));
        $this->assertContains('Pindah Site', $references['archive_reasons']);
        $this->assertContains('Dibuat', $references['history_kinds']);
        $this->assertNotContains('Dibuat', $references['history_actions']);
        $this->assertTrue($references['radio_id']['hex_f_refused']);

        $dashboard = $this->api('GET', '/api/v1/dashboard', token: $token)->assertOk()->json('data');
        $this->assertSame(1, $dashboard['total_active']);
        $this->assertSame(1, collect($dashboard['placements'])->firstWhere('value', 'di_user')['count']);
        $this->assertNotEmpty($dashboard['next_free_dmr_ids']);
    }
}
