<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * "Stock Opname" and "Import Excel" for whoever chose English: the screens, what their scripts are given to write, and
 * what the server says back are in English; what the history keeps, and the Excel format, stay in Indonesian.
 */
class OpnameImportLanguageTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use UsesEnglish;

    protected bool $seed = true;

    /** An upload as the page sends it, answered in JSON. */
    private const JSON = ['Accept' => 'application/json'];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function test_stock_opname_is_in_english_and_the_history_stays_in_indonesian(): void
    {
        $plant = RadioDepartment::query()->firstOrCreate(['name' => 'Bagian Pabrik'], ['is_active' => true]);
        $radio = fn (int $id) => Radio::create([
            'dmr_id' => $id, 'serial_number' => sprintf('867TZB%04d', $id), 'user_name' => "Pemakai {$id}",
            'department_id' => $plant->id, 'placement' => 'di_user', 'condition' => 'layak',
        ]);
        $here = $radio(1042);
        $other = $radio(1043);
        $this->actingAs($this->superAdmin());
        $this->useEnglish();

        $page = (string) $this->get(route('radio-management.opname.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="en">', $page);
        foreach (['Choose a department', 'All companies', 'Point at the SN barcode on the radio', 'Who has it now?', 'Someone else',
            'Send to the register', 'Back to scanning', 'Report a problem', '>Good<', '>Needs service<', '>Broken<'] as $english) {
            $this->assertStringContainsString($english, $page, $english);
        }
        foreach (['Pilih department', 'Semua company', 'Arahkan ke barcode', 'Siapa yang memegang', 'Kirim ke register', 'Laporkan masalah', 'Perlu Service'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $page, $indonesian);
        }
        // The script is given its words in English.
        $this->assertStringContainsString('"start":"Start a session"', $page);
        $this->assertStringContainsString('"not_found_text":":who. Last verified :date. Mark it lost and fill in the BA No. in Register Radio."', $page);

        $this->getJson(route('radio-management.opname.outlook'))->assertStatus(422)
            ->assertJsonPath('message', 'Invalid input')->assertJsonPath('errors.0', 'Choose the department to count.');
        $session = $this->postJson(route('radio-management.opname.start'), ['department_id' => $plant->id])->assertCreated()->json('data.session.id');
        $lookup = fn (string $q) => $this->getJson(route('radio-management.opname.lookup', ['opname' => $session, 'q' => $q]));
        $lookup('99999')->assertNotFound()->assertJsonPath('message', 'No radio in the register has that Radio ID or SN.');
        $lookup('1042')->assertOk()->assertJsonPath('data.placement', 'With user');

        $scan = fn (Radio $radio, array $other = []) => $this->putJson(route('radio-management.opname.scan', $session), $other + ['radio_id' => $radio->id, 'holder' => 'register', 'condition' => 'layak']);
        $scan($here, ['holder' => 'other'])->assertStatus(422)->assertJsonPath('errors.0', 'Write the name of whoever has the radio.');
        $scan($here)->assertOk()->assertJsonPath('message', 'Radio 1042 · MDC 412 verified.');
        $scan($other, ['holder' => 'other', 'holder_name' => 'Peminjam'])->assertOk()->assertJsonPath('data.recent.0.who', 'Held by Peminjam');
        $this->putJson(route('radio-management.opname.report', $session), ['radio_id' => $here->id, 'holder' => 'register', 'condition' => 'rusak'])
            ->assertStatus(422)->assertJsonPath('errors.0', 'Write the problem in the note first.');

        $this->putJson(route('radio-management.opname.submit', $session))->assertOk()->assertJsonPath('message', '2 radios verified in the register.');
        $this->putJson(route('radio-management.opname.submit', $session))->assertStatus(422)->assertJsonPath('errors.0', 'This session has been sent to the register.');
        // The line of the history is kept in Indonesian, whatever the language of the page.
        $this->assertSame('Stock opname Bagian Pabrik: dipegang sesuai register. Kondisi Layak.',
            RadioHistory::query()->where('radio_id', $here->id)->where('action', 'Verifikasi')->value('description'));
    }

    public function test_import_excel_is_in_english_and_its_refusals_too(): void
    {
        Storage::fake('local'); // keep the uploaded copies out of storage/app
        $this->actingAs($this->superAdmin());
        $this->useEnglish();

        $page = (string) $this->get(route('radio-management.import.index'))->assertOk()->getContent();
        foreach (['Import the radio register from Excel', 'Choose a file', 'No file chosen', e('Also import the sheet "to Konawe"'),
            'Delete every radio and all history before the import', 'Start the import', 'Last import', 'No import yet.',
            '<b>Location</b> + <b>Condition</b>'] as $english) {
            $this->assertStringContainsString($english, $page, $english);
        }
        foreach (['Impor register radio', 'Pilih file', 'Belum ada file dipilih', 'Mulai impor', 'Hasil impor terakhir', 'Belum ada impor'] as $indonesian) {
            $this->assertStringNotContainsString($indonesian, $page, $indonesian);
        }
        $this->assertStringContainsString('"pick_first":"Choose an Excel file (.xlsx) first."', $page);

        $this->postJson(route('radio-management.import.store'), [])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid input')->assertJsonPath('errors.0', 'Choose an Excel file (.xlsx) first.');
        $this->post(route('radio-management.import.store'), ['file' => UploadedFile::fake()->createWithContent('register.txt', 'SN;Radio ID')], self::JSON)
            ->assertStatus(422)->assertJsonPath('errors.0', 'The file must be an .xlsx file.');
        // The importer's reason: a workbook without the sheet "Form" (the name of the sheet is the register's own).
        $this->post(route('radio-management.import.store'), ['file' => $this->workbookWithout()], self::JSON)
            ->assertStatus(422)->assertJsonPath('message', 'Import cancelled')->assertJsonPath('errors.0', 'There is no sheet "Form" in this file.');

        // A register that holds radios: only an import that deletes them first goes ahead.
        Radio::create(['dmr_id' => 5, 'serial_number' => '867TZB0005', 'user_name' => 'x']);
        $this->post(route('radio-management.import.store'), ['file' => $this->workbookWithout()], self::JSON)->assertStatus(422)
            ->assertJsonPath('errors.0', 'The register already holds radios. Tick "Delete every radio and all history before the import" to import from Excel again.');
        $page = (string) $this->get(route('radio-management.import.index'))->assertOk()->getContent();
        $this->assertStringContainsString('The database holds <b>1</b> radio now.', $page);
    }

    /** An .xlsx without the sheet "Form". */
    private function workbookWithout(): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Lain');
        $path = $this->tempFiles[] = tempnam(sys_get_temp_dir(), 'rad');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'register.xlsx', null, null, true);
    }
}
