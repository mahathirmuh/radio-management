<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * Handing a radio over ("Serah terima" on its detail, 6.2): the radio becomes the receiver's, one history line with the BA,
 * one audit line; the receiver's signature is checked and then kept nowhere (it goes onto the printed form only).
 */
class RadioHandoverTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    /** A signature as the pad sends it: dark strokes on see-through paper. */
    private static function drawn(bool $ink = true): string
    {
        $picture = imagecreatetruecolor(400, 160);
        imagealphablending($picture, false);
        imagesavealpha($picture, true);
        imagefill($picture, 0, 0, imagecolorallocatealpha($picture, 0, 0, 0, 127));
        if ($ink) {
            imagesetthickness($picture, 4);
            imageline($picture, 20, 120, 200, 30, imagecolorallocatealpha($picture, 16, 16, 16, 0));
        }
        ob_start();
        imagepng($picture);
        $png = (string) ob_get_clean();
        imagedestroy($picture);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function radio(array $other = []): Radio
    {
        return Radio::create(['dmr_id' => 1044, 'serial_number' => '871TQC2208', 'user_name' => 'Pemakai Lama', 'placement' => 'stok_it', 'condition' => 'layak'] + $other);
    }

    private function form(array $other = []): array
    {
        return array_merge([
            'receiver' => 'Penerima Baru',
            'employee_id' => '20190342',
            'ba' => 'BA2300412',
            'po' => 'PO 4500187',
            'accessories' => ['Baterai', 'Charger', 'Antena'],
            'condition' => 'layak',
            'placement' => 'di_user',
            'department_id' => RadioDepartment::query()->value('id') ?? RadioDepartment::create(['name' => 'Bagian Contoh'])->id,
            'company_id' => RadioCompany::query()->value('id'),
            'signature' => self::drawn(),
        ], $other);
    }

    /** A user whose only role has these permissions on Register Radio. */
    private function withRadio(array $permissions, string $email): User
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Radio ' . implode(' ', $permissions), 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now()]);
        $menuId = DB::table('menu')->where('route', 'radio-management.radio.index')->value('id');
        DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('menu_role_permission')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS[$permission], 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->makeUser($email, (int) $roleId, 'Petugas');
    }

    public function test_a_handover_makes_the_radio_the_receivers_with_one_history_line_and_one_audit_line(): void
    {
        $radio = $this->radio();
        $form = $this->form();
        $this->actingAs($this->superAdmin());

        $this->putJson(route('radio-management.radio.handover', $radio), $form)->assertOk()
            ->assertJsonPath('message', 'Radio ' . $radio->display_name . ' diserahkan ke Penerima Baru.')
            ->assertJsonPath('checkout_form', route('radio-management.radio.checkout-form', $radio));

        $radio->refresh();
        $this->assertSame(['Penerima Baru', 'di_user', 'layak', 'PO 4500187 / BA2300412'], [$radio->user_name, $radio->placement->value, $radio->condition->value, $radio->document_no]);
        $this->assertSame([$form['department_id'], $form['company_id']], [(int) $radio->department_id, (int) $radio->company_id]);

        $line = RadioHistory::query()->where('radio_id', $radio->id)->where('action', 'Serah Terima')->sole();
        $this->assertSame('PO 4500187 / BA2300412', $line->document_no);
        $this->assertSame('Diserahkan ke Penerima Baru (20190342). Aksesori: Baterai, Charger, Antena.', $line->description);

        $audit = AuditLog::query()->where('action', 'radio.handed_over')->sole();
        $this->assertSame([
            'Penerima' => 'Penerima Baru',
            'No. karyawan' => '20190342',
            'No. BA' => 'BA2300412',
            'No. PO' => 'PO 4500187',
            'Aksesori' => 'Baterai, Charger, Antena',
            'Kondisi' => 'Layak',
            'Posisi' => 'Di User',
        ], $audit->new_values);
        // The signature is in no row of the database.
        foreach ([AuditLog::query()->get()->toJson(), RadioHistory::query()->get()->toJson(), Radio::query()->get()->toJson()] as $rows) {
            $this->assertStringNotContainsString('data:image', $rows);
        }

        // Without a PO, the BA alone; with no accessory, it says so.
        $this->putJson(route('radio-management.radio.handover', $radio), $this->form(['po' => '', 'accessories' => [], 'employee_id' => '']))->assertOk();
        $this->assertSame('BA2300412', $radio->fresh()->document_no);
        $this->assertSame('Diserahkan ke Penerima Baru. Tanpa aksesori.', RadioHistory::query()->where('action', 'Serah Terima')->latest('id')->value('description'));
    }

    public function test_a_signature_that_is_empty_missing_or_no_png_is_refused_and_nothing_is_changed(): void
    {
        $radio = $this->radio();
        $this->actingAs($this->superAdmin());
        $put = fn (array $other) => $this->putJson(route('radio-management.radio.handover', $radio), $this->form($other))->assertStatus(422);

        $this->assertContains('Tanda tangan masih kosong.', $put(['signature' => self::drawn(false)])->json('errors'));
        $this->assertContains('Penerima belum tanda tangan.', $put(['signature' => ''])->json('errors'));
        $this->assertContains('Tanda tangan harus berupa gambar PNG dari kotak tanda tangan.', $put(['signature' => 'data:image/jpeg;base64,AAAA'])->json('errors'));
        $put(['receiver' => '']);
        $put(['ba' => '']);
        $put(['accessories' => ['Payung']]);
        $put(['placement' => 'gudang']);

        $this->assertSame('Pemakai Lama', $radio->fresh()->user_name);
        $this->assertSame(0, RadioHistory::query()->where('action', 'Serah Terima')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'radio.handed_over')->count());
    }

    public function test_an_archived_radio_is_not_handed_over_and_who_only_reads_may_not(): void
    {
        $archived = $this->radio(['dmr_id' => null, 'previous_dmr_id' => 1044, 'archived_at' => now()]);
        $this->actingAs($this->superAdmin());
        $this->putJson(route('radio-management.radio.handover', $archived), $this->form())->assertStatus(422)
            ->assertJsonPath('errors.0', 'Radio yang diarsipkan tidak bisa diserahterimakan. Pulihkan dulu.');
        // Nor does its detail offer it.
        $this->get(route('radio-management.radio.detail', $archived))->assertOk()->assertDontSee('id="radioHandoverButton"', false);

        $radio = $this->radio(['dmr_id' => 2001, 'serial_number' => '871TQC2001']);
        $this->actingAs($this->withRadio(['Read'], 'baca@test.local'));
        $this->putJson(route('radio-management.radio.handover', $radio), $this->form())->assertForbidden();
        $this->getJson(route('radio-management.radio.employees', ['q' => 'budi']))->assertForbidden();
        $this->get(route('radio-management.radio.detail', $radio))->assertOk()->assertDontSee('id="radioHandoverButton"', false)->assertDontSee('id="radioHandoverModal"', false);

        $this->actingAs($this->withRadio(['Read', 'Update'], 'ubah@test.local'));
        $this->get(route('radio-management.radio.detail', $radio))->assertOk()->assertSee('id="radioHandoverButton"', false)->assertSee('id="radioHandoverModal"', false);
        // The employee directory is not set up in the tests: it says so, and finds nobody.
        $this->getJson(route('radio-management.radio.employees', ['q' => 'budi']))->assertOk()->assertJsonPath('available', false)->assertJsonPath('data', []);
        $this->putJson(route('radio-management.radio.handover', $radio), $this->form())->assertOk();
    }

    public function test_the_script_of_the_dialog_behaves_as_described(): void
    {
        $radio = $this->radio();
        $this->checkPageScript(route('radio-management.radio.detail', $radio), 'tests/tools/radio-handover-behaviour.mjs', 5);
    }
}
