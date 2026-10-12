<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\RadioManagement\Exports\RadioExport;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioConflicts;
use Modules\RadioManagement\Services\RadioIdService;
use Modules\RadioManagement\Services\RadioImportService;
use Modules\RadioManagement\Services\RadioService;
use Modules\RadioManagement\Services\RegisterLock;
use PDOException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * Rules of the register that came out of the review of the first version: imported
 * conflicts stay editable, concurrent saves, exports, permissions, search and import limits.
 */
class RadioRegisterRulesTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    /** @var string[] workbooks written by workbook() */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->superAdmin();
    }

    /**
     * A user whose only role has exactly the given grants, e.g.
     * ['radio-management.radio' => ['Read', 'Update']] (menu key => permission names).
     */
    private function userWithGrants(array $grants): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Role ' . count($grants) . '-' . uniqid(),
            'home_page' => '1',
            'func_role' => 'ALL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($grants as $menuKey => $permissions) {
            $menuId = DB::table('menu')->where('route', $menuKey . '.index')->value('id');
            $this->assertNotNull($menuId, "No menu for {$menuKey}");
            DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($permissions as $permission) {
                DB::table('menu_role_permission')->insert([
                    'menu_id' => $menuId,
                    'role_id' => $roleId,
                    'permission_id' => CheckMenuPermission::PERMISSION_IDS[$permission],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->makeUser(uniqid('grant') . '@test.local', (int) $roleId, 'Grant Tester');
    }

    private function typeId(): int
    {
        return RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'dmr_id' => 2001,
            'serial_number' => '867TZB9999',
            'radio_type_id' => $this->typeId(),
            'user_name' => 'Budi Santoso',
            'placement' => 'di_user',
            'condition' => 'layak',
        ], $overrides);
    }

    private function errors($response): string
    {
        return implode(' ', (array) $response->json('errors'));
    }

    /** A register workbook like the legacy one: sheet "Form", header row, then the given rows. */
    private function workbook(array $rows): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(RadioImportService::FORM_SHEET);
        $headers = ['No', 'SN', 'Radio ID', 'User Name', 'Department', 'Company', 'Radio Type', 'Status'];
        foreach (array_merge([$headers], $rows) as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                if ($value === null) {
                    continue;
                }
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($c + 1) . ($r + 1));
                $cell->setValueExplicit($value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'rad');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $this->tempFiles[] = $path;
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'register.xlsx', null, null, true);
    }

    // ------------------------------------------------------------------ editing imported conflicts

    public function test_an_imported_conflict_does_not_block_editing_until_the_value_itself_is_changed(): void
    {
        // The state the Excel import leaves behind: one Radio ID on two radios, an ID whose
        // hex form contains F, one SN on two radios, and an ID reserved by another radio.
        $twin = Radio::create(['dmr_id' => 601, 'serial_number' => '867TVP1379', 'user_name' => 'Arief']);
        Radio::create(['dmr_id' => 601, 'serial_number' => '866TTY1857', 'user_name' => 'BMS']);
        $hexF = Radio::create(['dmr_id' => 751, 'serial_number' => '867TZB0751', 'user_name' => 'Hex']); // 0x2EF
        $sameSn = Radio::create(['dmr_id' => 610, 'serial_number' => '752TYHC989', 'user_name' => 'Satu']);
        Radio::create(['dmr_id' => 611, 'serial_number' => '752TYHC989', 'user_name' => 'Dua']);
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 274, 'serial_number' => '867TZB0274', 'user_name' => 'At IT']);
        $holder = Radio::create(['dmr_id' => 274, 'serial_number' => '867TZB1274', 'user_name' => 'Pemegang']);
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 348, 'serial_number' => '867TZB0348', 'user_name' => 'At IT 2']);
        $this->actingAs($this->admin());

        $edit = fn (Radio $radio, array $changes) => $this->putJson(route('radio-management.radio.update', $radio), array_merge([
            'dmr_id' => $radio->dmr_id,
            'serial_number' => $radio->serial_number,
            'user_name' => $radio->user_name,
            'placement' => 'di_user',
            'condition' => 'layak',
        ], $changes));

        // Unchanged ID / SN: the rest of the radio can be saved.
        $edit($twin, ['user_name' => 'Arief Rahman'])->assertOk();
        $edit($hexF, ['condition' => 'rusak'])->assertOk();
        $edit($sameSn, ['user_name' => 'Satu Baru'])->assertOk();
        $edit($holder, ['user_name' => 'Pemegang Baru'])->assertOk();
        $this->assertSame('Arief Rahman', $twin->fresh()->user_name);
        // The SN is sent in another letter case and with a space: still "unchanged".
        $edit($sameSn, ['serial_number' => ' 752tyhc989 ', 'user_name' => 'Satu Lagi'])->assertOk();

        // Changing the value brings the checks back.
        $this->assertStringContainsString('sudah dipakai', $this->errors($edit($hexF, ['dmr_id' => 601])->assertStatus(422)));
        $this->assertStringContainsString('mengandung huruf F', $this->errors($edit($twin, ['dmr_id' => 2031])->assertStatus(422)));
        $this->assertStringContainsString('dicadangkan', $this->errors($edit($twin, ['dmr_id' => 348])->assertStatus(422)));
        $this->assertStringContainsString('sudah terdaftar', $this->errors($edit($twin, ['serial_number' => '752TYHC989'])->assertStatus(422)));

        // A free ID resolves the duplicate.
        $edit($twin, ['dmr_id' => 602])->assertOk();
        $this->assertSame(602, $twin->fresh()->dmr_id);
    }

    // ------------------------------------------------------------------ IDs whose MDC Code contains F

    public function test_an_id_whose_mdc_code_contains_f_is_refused_and_the_next_usable_ids_are_offered(): void
    {
        // 751 = 2EF. 752..767 = 2F0..2FF all contain F, 768 = 300 is the first one that does not.
        $this->assertSame(768, RadioIdService::nextWithoutHexF(751));
        $this->assertSame(768, RadioIdService::nextWithoutHexF(767));
        $this->assertSame(768, RadioIdService::nextWithoutHexF(768));
        $this->assertSame(16, RadioIdService::nextWithoutHexF(15));          // F -> 10
        $this->assertSame(4096, RadioIdService::nextWithoutHexF(3840));      // F00 -> 1000
        $this->assertSame(0x1000000, RadioIdService::nextWithoutHexF(0xEEEEEF)); // nothing left below 7 digits

        Radio::create(['dmr_id' => 768, 'serial_number' => '867TZB0768', 'user_name' => 'dipakai']);
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 769, 'serial_number' => '867TZB0769', 'user_name' => 'dicadangkan']);
        $this->actingAs($this->admin());

        // Typing it is refused, in words that say what to do.
        $response = $this->postJson(route('radio-management.radio.store'), $this->payload(['dmr_id' => 751]))->assertStatus(422);
        $this->assertStringContainsString(
            'Radio ID 751 tidak bisa dipakai: MDC Code-nya (2EF) mengandung huruf F. Ganti dengan ID lain.',
            $this->errors($response)
        );
        $this->assertSame(2, Radio::count());

        // The form asks for replacements just above it: used and reserved IDs are left out as well.
        $this->getJson(route('radio-management.radio.next-id', ['after' => 751]))->assertOk()->assertExactJson(['data' => [
            ['id' => 770, 'mdc' => '302'],
            ['id' => 771, 'mdc' => '303'],
            ['id' => 772, 'mdc' => '304'],
        ]]);
        // The "ID bebas" list: lowest free IDs, 15 (MDC F) is skipped.
        $this->assertSame([11, 12, 13, 14, 16], $this->getJson(route('radio-management.radio.next-id'))->json('data.*.id'));
        // Top of the range: EEEEEE is the last ID without an F, above it there is nothing to offer.
        $this->getJson(route('radio-management.radio.next-id', ['after' => 0xEEEEED]))->assertOk()->assertJsonPath('data.*.id', [0xEEEEEE]);
        $this->getJson(route('radio-management.radio.next-id', ['after' => 0xEEEEEE]))->assertOk()->assertExactJson(['data' => []]);
        $this->getJson(route('radio-management.radio.next-id', ['after' => 99999999999]))->assertOk()->assertExactJson(['data' => []]);

        // A radio that was imported with such an ID says so in the "Cek" column.
        Radio::create(['dmr_id' => 751, 'serial_number' => '867TZB0751', 'user_name' => 'impor']);
        $row = collect($this->getJson(route('radio-management.radio.list', ['issue' => 'id_hex_f']))->assertOk()->json('data'))->sole();
        $this->assertSame(751, $row['dmr_id']);
        $hint = collect($row['issues'])->firstWhere('code', 'id_hex_f')['hint'];
        $this->assertStringContainsString('MDC 2EF mengandung huruf F', $hint);
        $this->assertStringContainsString('Program ulang radio ke ID lain', $hint);
    }

    // ------------------------------------------------------------------ concurrent saves

    public function test_a_save_based_on_an_older_state_of_the_radio_is_refused(): void
    {
        $this->actingAs($this->admin());
        $this->postJson(route('radio-management.radio.store'), $this->payload())->assertOk();
        $radio = Radio::firstOrFail();

        $version = $this->getJson(route('radio-management.radio.show', $radio))->assertOk()->json('data.version');
        $this->assertNotEmpty($version);

        // First person saves: fine.
        $this->putJson(route('radio-management.radio.update', $radio), $this->payload(['user_name' => 'Andi', 'version' => $version]))
            ->assertOk();

        // Second person still has the form that was opened before that save.
        $response = $this->putJson(route('radio-management.radio.update', $radio), $this->payload(['user_name' => 'Citra', 'version' => $version]))
            ->assertStatus(422);
        $this->assertStringContainsString('baru saja diubah', $this->errors($response));
        $this->assertSame('Andi', $radio->fresh()->user_name);

        // Reopening the form gives the current version, and the save goes through.
        $current = $this->getJson(route('radio-management.radio.show', $radio))->json('data.version');
        $this->assertNotSame($version, $current);
        $this->putJson(route('radio-management.radio.update', $radio), $this->payload(['user_name' => 'Citra', 'version' => $current]))
            ->assertOk();
        $this->assertSame('Citra', $radio->fresh()->user_name);
    }

    public function test_conflicts_are_checked_again_inside_the_write_lock(): void
    {
        Radio::create(['dmr_id' => 300, 'serial_number' => '867TZB0300', 'user_name' => 'x']);

        // What a second request does when its form validation ran just before the first one saved.
        try {
            app(RadioService::class)->create([
                'dmr_id' => 300, 'serial_number' => '867TZB0301', 'user_name' => 'y',
                'placement' => 'di_user', 'condition' => 'layak',
            ], null);
            $this->fail('The Radio ID is already taken.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah dipakai', $e->validator->errors()->first('dmr_id'));
        }
        try {
            app(RadioService::class)->create([
                'dmr_id' => 301, 'serial_number' => '867TZB0300', 'user_name' => 'y',
                'placement' => 'di_user', 'condition' => 'layak',
            ], null);
            $this->fail('The SN is already registered.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sudah terdaftar', $e->validator->errors()->first('serial_number'));
        }

        $this->assertSame(1, Radio::count());
    }

    public function test_the_conflict_checks_run_while_the_write_lock_is_held(): void
    {
        $lock = new class extends RegisterLock
        {
            public bool $held = false;

            public function run(callable $callback, int $seconds = 10, int $wait = 5): mixed
            {
                $this->held = true;
                try {
                    return parent::run($callback, $seconds, $wait);
                } finally {
                    $this->held = false;
                }
            }
        };
        $conflicts = new class($lock) extends RadioConflicts
        {
            /** @var bool[] whether the lock was held each time a check ran */
            public array $seen = [];

            public function __construct(private RegisterLock $lock)
            {
            }

            public function radioId(int $id, ?int $exceptRadioId = null): ?string
            {
                $this->seen[] = $this->lock->held;

                return parent::radioId($id, $exceptRadioId);
            }

            public function serial(string $serial, ?int $exceptRadioId = null): ?string
            {
                $this->seen[] = $this->lock->held;

                return parent::serial($serial, $exceptRadioId);
            }
        };
        $this->app->instance(RegisterLock::class, $lock);
        $this->app->instance(RadioConflicts::class, $conflicts);

        $radio = app(RadioService::class)->create([
            'dmr_id' => 300, 'serial_number' => '867TZB0300', 'user_name' => 'x', 'placement' => 'di_user', 'condition' => 'layak',
        ], null);
        app(RadioService::class)->update($radio, ['dmr_id' => 301, 'serial_number' => '867TZB0301'], null);

        $this->assertSame([true, true, true, true], $conflicts->seen);
        $this->assertFalse($lock->held);
    }

    public function test_the_write_lock_is_held_during_a_write_and_released_after_it_also_a_failed_one(): void
    {
        $lock = app(RegisterLock::class);

        // While a write runs nobody else gets the lock.
        $ran = $lock->run(function () {
            $this->assertFalse(Cache::lock('radio-register-write', 1)->get(), 'The lock is not held while the write runs.');

            return true;
        });
        $this->assertTrue($ran);

        try {
            $lock->run(fn () => throw new RuntimeException('boom'));
            $this->fail('The exception must reach the caller.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame('ok', $lock->run(fn () => 'ok'));

        $probe = Cache::lock('radio-register-write', 5);
        $this->assertTrue($probe->get(), 'The register lock is still held.');
        $probe->release();
    }

    public function test_a_busy_register_answers_with_a_retry_message_instead_of_an_error_page(): void
    {
        $this->app->bind(RegisterLock::class, fn () => new class extends RegisterLock
        {
            public function run(callable $callback, int $seconds = 10, int $wait = 5): mixed
            {
                throw new LockTimeoutException();
            }
        });
        $this->actingAs($this->admin());

        $response = $this->postJson(route('radio-management.radio.store'), $this->payload())->assertStatus(409);
        $this->assertStringContainsString('sedang disimpan', $this->errors($response));
        $this->assertSame(0, Radio::count());

        // The import waits for its turn in the same way.
        Storage::fake('local');
        $file = $this->workbook([[1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak']]);
        $response = $this->post(route('radio-management.import.store'), ['file' => $this->upload($file)], ['Accept' => 'application/json'])
            ->assertStatus(409);
        $this->assertStringContainsString('Tunggu sebentar', $this->errors($response));
        $this->artisan('radio:import', ['file' => $file])->expectsOutputToContain('Coba lagi')->assertFailed();
        $this->assertSame(0, Radio::count());
    }

    public function test_master_data_removed_or_switched_off_meanwhile_is_answered_with_a_message(): void
    {
        $gone = RadioDepartment::create(['name' => 'DIHAPUS'])->id;
        RadioDepartment::whereKey($gone)->delete();
        $off = ChannelTemplate::create(['name' => 'Template Lama', 'is_active' => false])->id;
        $data = ['dmr_id' => 310, 'serial_number' => '867TZB0310', 'user_name' => 'x', 'placement' => 'di_user', 'condition' => 'layak'];

        // What reaches the service when the row was still fine while the form was validated.
        try {
            app(RadioService::class)->create($data + ['department_id' => $gone, 'channel_template_id' => $off], null);
            $this->fail('A deleted department and an inactive template must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame('Department tidak ditemukan di master data.', $e->validator->errors()->first('department_id'));
            $this->assertStringContainsString('sudah dinonaktifkan', $e->validator->errors()->first('channel_template_id'));
        }
        $this->assertSame(0, Radio::count());

        // A radio that already has the inactive template keeps it.
        $radio = Radio::create($data + ['channel_template_id' => $off]);
        app(RadioService::class)->update($radio, ['channel_template_id' => $off, 'user_name' => 'y'], null);
        $this->assertSame('y', $radio->fresh()->user_name);
    }

    public function test_archiving_or_restoring_twice_is_refused_the_second_time(): void
    {
        $this->actingAs($this->admin());
        $radio = Radio::create(['dmr_id' => 40, 'serial_number' => '867TZB0040', 'user_name' => 'x']);
        $archive = ['archive_reason' => 'Pindah Site', 'archived_at' => now()->toDateString()];

        $this->putJson(route('radio-management.radio.restore', $radio))->assertStatus(422);
        $this->putJson(route('radio-management.radio.archive', $radio), $archive)->assertOk();
        $this->putJson(route('radio-management.radio.archive', $radio), $archive)->assertStatus(422);
        $this->putJson(route('radio-management.radio.restore', $radio))->assertOk();
        $this->putJson(route('radio-management.radio.restore', $radio))->assertStatus(422);

        $this->assertSame(['Dipulihkan', 'Arsip'], $radio->histories()->pluck('action')->all());
        $this->assertSame(40, $radio->fresh()->dmr_id);
    }

    public function test_restoring_gives_the_old_radio_id_back_unless_another_radio_has_it_now(): void
    {
        $this->actingAs($this->admin());
        $archive = ['archive_reason' => 'Afkir', 'archived_at' => now()->toDateString()];

        // 175 = 0xAF. An imported ID with F is tolerated, so archive + restore must not strip it.
        $hexF = Radio::create(['dmr_id' => 175, 'serial_number' => '867TZB0175', 'user_name' => 'x']);
        $this->putJson(route('radio-management.radio.archive', $hexF), $archive)->assertOk();
        $this->putJson(route('radio-management.radio.restore', $hexF))->assertOk()->assertJsonPath('dmr_id', 175);
        $this->assertStringContainsString('Radio ID 175 dipakai kembali', $hexF->histories()->first()->description);

        // Taken by another radio in the meantime: stays empty, and the log says why.
        $old = Radio::create(['dmr_id' => 180, 'serial_number' => '867TZB0180', 'user_name' => 'x']);
        $this->putJson(route('radio-management.radio.archive', $old), $archive)->assertOk();
        Radio::create(['dmr_id' => 180, 'serial_number' => '867TZB1180', 'user_name' => 'y']);
        $this->putJson(route('radio-management.radio.restore', $old))->assertOk()->assertJsonPath('dmr_id', null);
        $this->assertStringContainsString('dipakai atau dicadangkan radio lain', $old->histories()->first()->description);
        $this->assertSame(180, $old->fresh()->previous_dmr_id);
    }

    // ------------------------------------------------------------------ export

    public function test_export_writes_mdc_codes_and_texts_as_text(): void
    {
        // 1001 = 0x3E9: Excel would read "3E9" as 3 x 10^9. A text starting with "=" would become a formula.
        Radio::create([
            'dmr_id' => 1001, 'serial_number' => '867TZB1001', 'user_name' => '=SUM(1,1)',
            'notes' => '=1+1', 'document_no' => '00123',
        ]);
        $this->actingAs($this->admin());

        $response = $this->get(route('radio-management.radio.export'))->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame('Radio ID', $sheet->getCell('A1')->getValue());
        $this->assertSame(1001, $sheet->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('A2')->getDataType());
        $this->assertSame('3E9', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        // (The columns by their titles: "Owner" stands after "Pemilik Aset", in G and H.)
        $this->assertSame(['Pemilik Aset', 'Owner', 'Nama User / Unit'], [$sheet->getCell('G1')->getValue(), $sheet->getCell('H1')->getValue(), $sheet->getCell('I1')->getValue()]);
        $this->assertSame('=SUM(1,1)', $sheet->getCell('I2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('I2')->getDataType());
        $this->assertSame('00123', $sheet->getCell('Q2')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('R2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('R2')->getDataType());

        // The export must not stay PhpSpreadsheet's value binder: a workbook handled later in the
        // same process would get every number as text.
        $this->assertNotInstanceOf(RadioExport::class, Cell::getValueBinder());
        $probe = (new Spreadsheet())->getActiveSheet();
        $probe->setCellValue('A1', '123');
        $this->assertSame(DataType::TYPE_NUMERIC, $probe->getCell('A1')->getDataType());
    }

    // ------------------------------------------------------------------ master data

    public function test_master_data_used_only_by_a_deleted_radio_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $company = RadioCompany::create(['code' => 'OLD', 'name' => 'PT Lama']);
        $radio = Radio::create(['serial_number' => '867TZB1111', 'user_name' => 'x', 'company_id' => $company->id, 'owner_company_id' => $company->id]);

        // Still in the register: refused, and the count matches what the list shows
        // (one radio, although it names the company twice).
        $this->getJson(route('radio-management.company.list', ['search' => 'OLD']))->assertJsonPath('data.0.usage', 1);
        $response = $this->deleteJson(route('radio-management.company.destroy', $company->id))->assertStatus(422);
        $this->assertStringContainsString('Masih dipakai 1 radio', $this->errors($response));

        // Radio removed from the register ("Hapus"): the list shows 0 and the delete works.
        $this->deleteJson(route('radio-management.radio.destroy', $radio))->assertOk();
        $this->getJson(route('radio-management.company.list', ['search' => 'OLD']))->assertJsonPath('data.0.usage', 0);
        $this->deleteJson(route('radio-management.company.destroy', $company->id))->assertOk();

        $this->assertFalse(RadioCompany::where('code', 'OLD')->exists());
        $deleted = Radio::withTrashed()->findOrFail($radio->id);
        $this->assertNull($deleted->company_id);
        $this->assertNull($deleted->owner_company_id);
    }

    public function test_inactive_master_data_stays_on_its_radios_but_cannot_be_picked_again(): void
    {
        $this->actingAs($this->admin());
        $department = RadioDepartment::create(['name' => 'COMMISSIONING']);
        $this->postJson(route('radio-management.radio.store'), $this->payload(['department_id' => $department->id]))->assertOk();
        $radio = Radio::firstOrFail();

        $department->update(['is_active' => false]);

        // The radio that already has it can still be saved.
        $this->putJson(route('radio-management.radio.update', $radio), $this->payload(['department_id' => $department->id, 'user_name' => 'Andi']))
            ->assertOk();

        // Another radio cannot take it.
        $response = $this->postJson(route('radio-management.radio.store'), $this->payload([
            'dmr_id' => 2002, 'serial_number' => '867TZB0002', 'department_id' => $department->id,
        ]))->assertStatus(422);
        $this->assertStringContainsString('Department yang dipilih sudah dinonaktifkan', $this->errors($response));
    }

    // ------------------------------------------------------------------ permissions

    public function test_wiping_the_register_through_import_needs_delete_on_the_register(): void
    {
        Storage::fake('local'); // keep the uploaded copies out of storage/app
        $file = $this->workbook([[1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak']]);

        // May import, may read the register, may not delete radios.
        $importer = $this->userWithGrants([
            'radio-management.import' => ['Create', 'Read'],
            'radio-management.radio' => ['Read'],
        ]);
        $this->actingAs($importer);

        // Empty register: a plain import is what "Create" on Import Excel is for.
        $this->post(route('radio-management.import.store'), ['file' => $this->upload($file), 'fresh' => 0], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('report.imported', 1);

        // Replacing the register deletes every radio and its history.
        $response = $this->post(route('radio-management.import.store'), ['file' => $this->upload($file), 'fresh' => 1], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->assertStringContainsString('izin Delete', $this->errors($response));
        $this->assertSame(1, Radio::count());
        $this->get(route('radio-management.import.index'))->assertOk()->assertDontSee('id="importFresh"', false);

        // With Delete on Register Radio the same request goes through.
        $deleter = $this->userWithGrants([
            'radio-management.import' => ['Create', 'Read'],
            'radio-management.radio' => ['Read', 'Delete'],
        ]);
        $this->actingAs($deleter);
        $this->get(route('radio-management.import.index'))->assertOk()->assertSee('id="importFresh"', false);
        $this->post(route('radio-management.import.store'), ['file' => $this->upload($file), 'fresh' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('report.imported', 1);
        $this->assertSame(1, Radio::withTrashed()->count());
    }

    public function test_adding_a_history_line_needs_update_not_create(): void
    {
        $radio = Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB5309', 'user_name' => 'Iqbal']);
        $line = ['action' => 'Service', 'event_date' => now()->toDateString(), 'description' => 'Ganti antena'];

        $this->actingAs($this->userWithGrants(['radio-management.radio' => ['Read', 'Create']]));
        $this->putJson(route('radio-management.radio.add-history', $radio), $line)->assertForbidden();

        $this->actingAs($this->userWithGrants(['radio-management.radio' => ['Read', 'Update']]));
        $this->putJson(route('radio-management.radio.add-history', $radio), $line)->assertOk();

        $this->assertSame(1, RadioHistory::where('radio_id', $radio->id)->where('action', 'Service')->count());
    }

    // ------------------------------------------------------------------ search and checks

    public function test_search_takes_the_typed_text_literally_and_finds_mdc_codes_made_of_digits(): void
    {
        $bracket = Radio::create(['dmr_id' => 100, 'serial_number' => '867TZB0100', 'user_name' => 'LV 51 [NEW]', 'notes' => 'baterai 50% drop']);
        $underscore = Radio::create(['dmr_id' => 256, 'serial_number' => '867TZB0256', 'user_name' => 'POS_LABOTA']); // MDC 100
        $letters = Radio::create(['dmr_id' => 300, 'serial_number' => '867TZB0300', 'user_name' => 'Wawan N E W']);
        RadioDepartment::create(['name' => 'R & D [LAB]']);
        $this->actingAs($this->admin());

        $search = fn (string $term) => $this->getJson(route('radio-management.radio.list', ['search' => $term]))->assertOk();

        // On SQL Server "[NEW]" is a character class: without escaping it matches every row with N, E or W.
        $search('[NEW]')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.dmr_id', 100);
        $search('50%')->assertJsonPath('meta.total', 1);
        $search('%')->assertJsonPath('meta.total', 1);
        $search('POS_L')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.dmr_id', 256);
        $search('_')->assertJsonPath('meta.total', 1);
        // "100" is Radio ID 100 and also the MDC code of Radio ID 256.
        $this->assertEqualsCanonicalizing([100, 256], $search('100')->json('data.*.dmr_id'));

        // History: one line matches by its own text, one through its radio's user name, and one
        // only has the letters N, E, W, which is what an unescaped "[NEW]" would match.
        $log = app(RadioService::class);
        $log->log($underscore, 'Service', 'Label [NEW] dipasang', null);
        $log->log($bracket, 'Program', 'Program ulang', null);
        $log->log($letters, 'Service', 'Antena W E N diganti', null);
        $history = $this->getJson(route('radio-management.history.list', ['search' => '[NEW]']))->assertOk();
        $this->assertEqualsCanonicalizing(['Label [NEW] dipasang', 'Program ulang'], $history->json('data.*.description'));
        $this->getJson(route('radio-management.history.list', ['search' => '[WEN]']))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(route('radio-management.department.list', ['search' => '[LAB]']))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(route('radio-management.department.list', ['search' => '[RD]']))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_a_duplicated_all_digit_serial_number_does_not_break_the_checks(): void
    {
        Radio::create(['dmr_id' => 50, 'serial_number' => '12345678', 'user_name' => 'x']);
        Radio::create(['dmr_id' => 51, 'serial_number' => '12345678', 'user_name' => 'y']);
        Radio::create(['dmr_id' => 52, 'serial_number' => '867TZB0052', 'user_name' => 'z']);
        $this->actingAs($this->admin());

        $this->getJson(route('radio-management.radio.list', ['issue' => 'sn_duplicate']))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_text_with_special_characters_is_stored_and_returned_exactly_as_typed(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('radio-management.department.store'), ['name' => 'E & I <Elektrik>', 'is_active' => 1])->assertOk();
        $department = RadioDepartment::where('name', 'E & I <ELEKTRIK>')->firstOrFail(); // upper-cased, nothing else
        $this->postJson(route('radio-management.radio.store'), $this->payload([
            'user_name' => "Rudi & Tim <A>", 'notes' => 'baterai <50% & antena "longgar"', 'department_id' => $department->id,
        ]))->assertOk();
        $radio = Radio::firstOrFail();
        $this->assertSame('Rudi & Tim <A>', $radio->user_name);
        $this->assertSame('baterai <50% & antena "longgar"', $radio->notes);

        // Saving the form again without touching anything changes nothing and logs nothing.
        $form = $this->getJson(route('radio-management.radio.show', $radio))->assertOk()->json('data');
        $this->putJson(route('radio-management.radio.update', $radio), $form)->assertOk();
        $this->assertSame('Rudi & Tim <A>', $radio->fresh()->user_name);
        $this->assertSame(['Dibuat'], $radio->histories()->pluck('action')->all());

        // The pages escape on output.
        $this->get(route('radio-management.radio.detail', $radio))->assertOk()
            ->assertSee('Rudi &amp; Tim &lt;A&gt;', false)
            ->assertDontSee('Tim <A>', false);
    }

    // ------------------------------------------------------------------ history

    public function test_history_keeps_the_name_of_a_user_who_was_deleted_later(): void
    {
        $editor = $this->userWithGrants(['radio-management.radio' => ['Read', 'Create', 'Update'], 'radio-management.history' => ['Read']]);
        $this->actingAs($editor);
        $this->postJson(route('radio-management.radio.store'), $this->payload())->assertOk();
        $radio = Radio::firstOrFail();

        $editor->roles()->detach();
        $editor->delete(); // what User Management > delete does

        $this->actingAs($this->admin());
        $this->getJson(route('radio-management.history.list'))->assertOk()->assertJsonPath('data.0.user', 'Grant Tester');
        $this->get(route('radio-management.radio.detail', $radio))->assertOk()->assertSee('oleh Grant Tester');
    }

    // ------------------------------------------------------------------ import limits

    public function test_import_flags_values_that_do_not_fit_instead_of_aborting(): void
    {
        $longSerial = str_repeat('8', 60);
        $longUser = trim(str_repeat('Nama Panjang ', 20)); // 259 characters
        $file = $this->workbook([
            [1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak'],
            // A serial number typed into the Radio ID column: far above any Radio ID.
            [2, '867TZB5310', 8675310999.0, 'Salah Kolom', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak'],
            [3, $longSerial, 22, $longUser, 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak'],
            [4, '867TZB5312', 601.5, 'Salah Ketik', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak'],
        ]);

        $report = app(RadioImportService::class)->import($file);

        $this->assertSame(4, $report['imported']);
        $this->assertSame(3, $report['flagged']);
        $this->assertStringContainsString('tidak valid ("601.5")', Radio::where('serial_number', '867TZB5312')->value('review_notes'));

        $wrongColumn = Radio::where('serial_number', '867TZB5310')->firstOrFail();
        $this->assertNull($wrongColumn->dmr_id);
        $this->assertSame(21, $wrongColumn->previous_dmr_id); // slot position, kept as last known ID
        $this->assertStringContainsString('tidak valid ("8675310999")', $wrongColumn->review_notes);

        $long = Radio::where('dmr_id', 22)->firstOrFail();
        $this->assertSame(str_repeat('8', 50), $long->serial_number);
        $this->assertSame(150, mb_strlen($long->user_name));
        $this->assertTrue($long->needs_review);
        $this->assertStringContainsString('SN di Excel lebih panjang dari 50 karakter', $long->review_notes);
        $this->assertStringContainsString('User Name di Excel lebih panjang dari 150 karakter', $long->review_notes);
        $this->assertSame($longSerial, $long->source_data['SN']); // the full value is still on record
    }

    public function test_import_takes_one_long_turn_on_the_register_and_rechecks_that_it_is_empty(): void
    {
        $file = $this->workbook([[1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak']]);

        // Somebody else's save gets the lock first, after this import found the register empty.
        $lock = new class extends RegisterLock
        {
            /** @var int[] */
            public array $seconds = [];

            public function run(callable $callback, int $seconds = 10, int $wait = 5): mixed
            {
                $this->seconds[] = $seconds;
                Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB9020', 'user_name' => 'Datang lebih dulu']);

                return parent::run($callback, $seconds, $wait);
            }
        };
        $this->app->instance(RegisterLock::class, $lock);

        try {
            app(RadioImportService::class)->import($file);
            $this->fail('The register is no longer empty.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Data radio sudah ada', $e->getMessage());
        }

        $this->assertSame(['867TZB9020'], Radio::pluck('serial_number')->all());
        $this->assertSame([600], $lock->seconds); // long enough for the whole write
    }

    public function test_a_database_error_during_import_is_logged_not_shown(): void
    {
        Storage::fake('local');
        $file = $this->workbook([[1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak']]);
        $error = new QueryException('sqlsrv', 'insert into [radios] ([serial_number]) values (?)', ['867TZB5309'], new PDOException('String or binary data would be truncated'));
        $this->mock(RadioImportService::class)->shouldReceive('import')->andThrow($error);
        Log::spy();
        $this->actingAs($this->admin());

        $response = $this->post(route('radio-management.import.store'), ['file' => $this->upload($file)], ['Accept' => 'application/json'])
            ->assertStatus(500);
        $this->assertStringContainsString('kesalahan database', $this->errors($response));
        $this->assertStringNotContainsString('insert into', $response->getContent());

        $this->artisan('radio:import', ['file' => $file])
            ->expectsOutputToContain('kesalahan database')
            ->doesntExpectOutputToContain('insert into')
            ->assertFailed()
            ->run();

        // Once for the web request, once for the command: the details are on record.
        Log::shouldHaveReceived('error')
            ->with('Radio import failed', Mockery::on(fn ($context) => ($context['exception'] ?? null) === $error))
            ->twice();
    }

    public function test_a_lock_left_behind_by_an_import_that_was_cut_off_can_be_released(): void
    {
        // What a killed import leaves in the cache: the lock, with minutes still to run.
        $this->assertTrue(Cache::lock('radio-register-write', 600)->get());
        $this->assertFalse(Cache::lock('radio-register-write', 1)->get());

        $this->artisan('radio:unlock')->assertSuccessful();

        $this->assertSame('ok', app(RegisterLock::class)->run(fn () => 'ok'));
    }
}
