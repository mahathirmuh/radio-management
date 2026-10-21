<?php

namespace Tests\Feature\Api;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Api\Providers\ApiServiceProvider;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioImports;
use Modules\RadioManagement\Services\RadioImportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * What goes in and out of the register as files through the API: the Excel import, the Excel export, and the data
 * of the printed checkout form. The same rules as the pages.
 */
class ApiImportExportTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        // The uploaded copies stay out of storage/app, which the live application shares.
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** A workbook as the import reads it: the sheet "Form" with its headers, and these rows. */
    private function workbook(array $rows, string $sheet = RadioImportService::FORM_SHEET): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle($sheet);
        $headers = ['No', 'SN', 'Radio ID', 'User Name', 'Department', 'Company', 'Radio Type', 'Status'];
        foreach (array_merge([$headers], $rows) as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                if ($value !== null) {
                    $book->getActiveSheet()->getCell(Coordinate::stringFromColumnIndex($c + 1) . ($r + 1))
                        ->setValueExplicit($value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
                }
            }
        }
        $path = $this->tempFiles[] = tempnam(sys_get_temp_dir(), 'rad');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'register.xlsx', null, null, true);
    }

    private function radiosOf(string $path): array
    {
        $book = IOFactory::load($path);
        $rows = $book->getActiveSheet()->toArray();
        $book->disconnectWorksheets();

        return $rows;
    }

    public function test_an_empty_register_is_filled_from_excel_and_the_report_is_kept(): void
    {
        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio', 'Andi Admin');
        $token = $this->tokenFor($admin, name: 'Migrasi');
        $this->api('GET', '/api/v1/import', token: $token)->assertOk()->assertExactJson(['data' => ['radios_in_register' => 0, 'last_import' => null]]);

        $report = $this->apiUpload('/api/v1/import', ['file' => $this->workbook([
            [1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak'],
            [2, '867TZB5310', 21, 'Budi', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak'],
        ])], $token)->assertOk()->assertJsonPath('message', 'Import finished.')->json('data');
        $this->assertSame([2, 'register.xlsx', 'Andi Admin'], [$report['imported'], $report['file'], $report['by']]);
        $this->assertSame(2, Radio::count());

        // The page and the API show the same last report.
        $this->assertSame(2, $this->api('GET', '/api/v1/import', token: $token)->json('data.last_import.imported'));
        $this->assertSame(2, app(RadioImports::class)->lastReport()['imported']);

        // One entry in the audit trail, through the token.
        $entry = AuditLog::where('action', AuditLogger::RADIO_IMPORTED)->sole();
        $this->assertSame(['register.xlsx', 2], [$entry->new_values['File'], $entry->new_values['Diimpor']]);
        $this->assertMatchesRegularExpression('/^API · Migrasi \(#\d+\)$/', (string) $entry->via);
    }

    public function test_an_import_is_refused_as_on_the_page(): void
    {
        $token = $this->tokenFor($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $file = fn () => $this->workbook([[1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak']]);

        // No file, not a workbook.
        $this->apiUpload('/api/v1/import', [], $token)->assertStatus(422)->assertJsonValidationErrors(['file']);
        $text = UploadedFile::fake()->createWithContent('register.txt', 'SN;Radio ID');
        $this->apiUpload('/api/v1/import', ['file' => $text], $token)->assertStatus(422)->assertJsonValidationErrors(['file']);

        // A workbook without the sheet "Form": the importer's reason, by the field.
        $this->apiUpload('/api/v1/import', ['file' => $this->workbook([], 'Lain')], $token)
            ->assertStatus(422)->assertJsonValidationErrors(['file'])->assertJsonPath('errors.file.0', 'There is no sheet "Form" in this file.');

        // The register is not empty: only "fresh" imports again.
        Radio::create(['dmr_id' => 5, 'serial_number' => '867TZB0005', 'user_name' => 'x']);
        $this->apiUpload('/api/v1/import', ['file' => $file()], $token)->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->assertSame(1, Radio::count());
        $this->apiUpload('/api/v1/import', ['file' => $file(), 'fresh' => 1], $token)->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertSame(['867TZB5309'], Radio::pluck('serial_number')->all());
    }

    public function test_replacing_the_register_needs_delete_on_register_radio(): void
    {
        Radio::create(['dmr_id' => 5, 'serial_number' => '867TZB0005', 'user_name' => 'x']);
        $file = fn () => $this->workbook([[1, '867TZB5309', 20, 'Iqbal', 'IT', 'MTI', 'MotoTrbo XiR P6620i', 'Layak']]);
        $importer = $this->tokenFor($this->makeUser('importer@test.local', $this->roleWith([
            'radio-management.import' => ['Read', 'Create'],
            'radio-management.radio' => ['Read'],
        ])));

        $this->apiUpload('/api/v1/import', ['file' => $file(), 'fresh' => 1], $importer)
            ->assertForbidden()->assertJsonPath('message', 'Deleting the old data needs the Delete permission on the Register Radio menu.');
        $this->assertSame(['867TZB0005'], Radio::pluck('serial_number')->all());

        $deleter = $this->tokenFor($this->makeUser('deleter@test.local', $this->roleWith([
            'radio-management.import' => ['Read', 'Create'],
            'radio-management.radio' => ['Read', 'Delete'],
        ])));
        $this->apiUpload('/api/v1/import', ['file' => $file(), 'fresh' => 1], $deleter)->assertOk();

        // Read only: not even an empty register.
        $reader = $this->tokenFor($this->makeUser('reader@test.local', 'Admin Radio'), write: false);
        $this->apiUpload('/api/v1/import', ['file' => $file(), 'fresh' => 1], $reader)->assertForbidden();
        // Viewer Radio reads the last report, but does not import.
        $viewer = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'));
        $this->api('GET', '/api/v1/import', token: $viewer)->assertOk();
        $this->apiUpload('/api/v1/import', ['file' => $file(), 'fresh' => 1], $viewer)->assertForbidden();
    }

    public function test_the_export_is_the_excel_file_of_the_page_with_the_filters_of_the_list(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'), write: false);
        $type = RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id');
        $department = RadioDepartment::firstOrCreate(['name' => 'MAINTENANCE'])->id;
        Radio::create(['dmr_id' => 101, 'serial_number' => '867TZB0101', 'user_name' => 'Satu', 'radio_type_id' => $type, 'department_id' => $department]);
        Radio::create(['dmr_id' => 102, 'serial_number' => '867TZB0102', 'user_name' => 'Dua', 'radio_type_id' => $type, 'placement' => 'stok_it']);
        Radio::create(['dmr_id' => null, 'previous_dmr_id' => 103, 'serial_number' => '867TZB0103', 'user_name' => 'Arsip', 'archived_at' => now()]);

        $all = $this->api('GET', '/api/v1/radios/export', token: $token)->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $all->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('/attachment; filename=register-radio-\d{8}-\d{6}\.xlsx/', (string) $all->headers->get('Content-Disposition'));
        $rows = $this->radiosOf($all->baseResponse->getFile()->getPathname());
        $this->assertCount(3, $rows, 'the headers and the two active radios');
        $this->assertContains('867TZB0101', $rows[1]);

        // The filters of the list.
        $stock = $this->radiosOf($this->api('GET', '/api/v1/radios/export?placement=stok_it', token: $token)->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertCount(2, $stock);
        $this->assertContains('867TZB0102', $stock[1]);
        $this->assertCount(4, $this->radiosOf($this->api('GET', '/api/v1/radios/export?scope=all', token: $token)->baseResponse->getFile()->getPathname()));
        $this->api('GET', '/api/v1/radios/export?scope=semua', token: $token)->assertStatus(422)->assertJsonValidationErrors(['scope']);

        // Not for a role without the register.
        $this->api('GET', '/api/v1/radios/export', token: $this->tokenFor($this->makeUser('doc@test.local', 'Document Controller')))->assertForbidden();
    }

    public function test_export_and_import_have_a_tighter_limit(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'));
        for ($i = 1; $i <= ApiServiceProvider::HEAVY_PER_TOKEN; $i++) {
            $this->api('GET', '/api/v1/radios/export', token: $token)->assertOk();
        }
        $this->api('GET', '/api/v1/radios/export', token: $token)->assertStatus(429);
        // The rest of the API is not held up by it.
        $this->api('GET', '/api/v1/radios', token: $token)->assertOk();
    }

    public function test_the_checkout_form_is_what_the_printed_form_fills_in(): void
    {
        $token = $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio'), write: false);
        $radio = Radio::create([
            'dmr_id' => 1010, 'serial_number' => '867TBF3759', 'user_name' => 'Budi Santoso',
            'radio_type_id' => RadioType::where('name', 'MotoTrbo XiR P6620i')->value('id'),
            'department_id' => RadioDepartment::firstOrCreate(['name' => 'MAINTENANCE'])->id,
        ]);

        $form = $this->api('GET', "/api/v1/radios/{$radio->id}/checkout-form", token: $token)->assertOk()->json('data');
        $this->assertSame(['MTI-HRM-ICT-FRM-001', 'Rev.000'], [$form['form']['number'], $form['form']['revision']]);
        $this->assertSame(['HT', 'RIG', now()->format('d-m-Y')], [$form['category'], $form['struck'], $form['date']]);
        $this->assertSame([
            'requestor_name' => 'Budi Santoso',
            'requestor_department' => 'MAINTENANCE',
            'radio_id' => '1010',
            'radio_type' => 'MotoTrbo XiR P6620i',
            'radio_serial' => '867TBF3759',
            'user_name' => 'Budi Santoso',
            'user_department' => 'MAINTENANCE',
            'ict_date' => now()->format('d-m-Y'),
        ], $form['fields']);

        // Each box holds what the printed form of the page has in it.
        $this->actingAs($this->superAdmin());
        $page = (string) $this->get(route('radio-management.radio.checkout-form', $radio))->assertOk()->getContent();
        foreach ($form['fields'] as $box => $value) {
            $this->assertMatchesRegularExpression('#data-field="' . preg_quote($box, '#') . '">\s*' . preg_quote(e($value), '#') . '\s*<#', $page, $box);
        }

        // No signature leaves through the API, and it is no use of one.
        $this->assertStringNotContainsString('image', json_encode($form));
        $this->assertFalse(AuditLog::where('action', AuditLogger::ESIGN_USED)->where('via', 'like', 'API%')->exists());
        $this->api('GET', '/api/v1/radios/999999/checkout-form', token: $token)->assertNotFound();
    }
}
