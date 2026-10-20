<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use Database\Seeders\CofScanMenuSeeder;
use Database\Seeders\IctMasterlistMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as GraphRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Modules\SharePointDocs\Services\GraphClient;
use Modules\SharePointDocs\Services\ScanLinks;
use Modules\SharePointDocs\Services\ScanRegister;
use Modules\SharePointDocs\Services\SharePointFolder;
use Modules\SharePointDocs\Services\SharePointWorkbook;
use Modules\SharePointDocs\Services\WorkbookReader;
use Tests\Concerns\MakesTransfers;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\MakesWorkbooks;
use Tests\TestCase;

/**
 * The COF sheet of the workbook and the COF Scan folder set against each other: the sheet's column "File scan" and the cards'
 * counts on the page of the workbook, the "Masterlist" and "Catatan" of every file on the page of the folder. Microsoft is
 * played by Http::fake() (the workbook and the folder both); every name and address is made up.
 */
class SharePointScansTest extends TestCase
{
    use MakesTransfers;
    use MakesUsers;
    use MakesWorkbooks;
    use RefreshDatabase;

    protected bool $seed = true;

    private const BOOK_ADDRESS = 'https://contoso.sharepoint.com/:x:/r/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B11111111-2222-3333-4444-555555555555%7D&file=Daftar%20Induk.xlsx&action=default';

    private const FOLDER_ADDRESS = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Forms/AllItems.aspx?id=%2Fsites%2FTeam%2FShared%20Documents%2FScans&viewid=1';

    private const FOLDER_URL = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Scans';

    private const DOWNLOAD = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/download.aspx';

    private const NOW = '2026-10-07 02:15:00';

    /** @var array<string, callable> what the pretend Microsoft answers: 'token', 'book', 'folder', 'content', 'download', 'children' */
    private array $microsoft = [];

    private string $bytes = '';

    /** The version of the workbook's file (its eTag): another one makes the application read the file again. */
    private int $version = 1;

    /** @var array<int, array{0: string, 1: array}> every request's address and the options it was sent with */
    private array $sentWith = [];

    /** When the test process says its request began (put back afterwards: the tests are not to count the age of the process in their budgets). */
    private ?float $began = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->began = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 3600;
        Sleep::fake();
        config([
            'logging.default' => 'null',
            'services.azure.tenant' => 'tenant-1',
            'services.azure.client_id' => 'client-1',
            'services.azure.client_secret' => 'secret-1',
            'sharepoint-docs.folders.cof-scan.url' => self::FOLDER_ADDRESS,
            'sharepoint-docs.workbooks.ict-masterlist.url' => self::BOOK_ADDRESS,
            'sharepoint-docs.workbooks.ict-masterlist.sheets' => null,
            'sharepoint-docs.workbooks.ict-masterlist.skip_unused_rows' => false,
            // As the real workbook is tied to its folder, with the titles this made-up workbook has.
            'sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['folder' => 'cof-scan', 'link' => 'Tautan', 'code' => 'COF No', 'note' => 'Catatan']],
            'sharepoint-docs.cache_minutes' => 10,
            'sharepoint-docs.time_limit' => 20,
            'sharepoint-docs.preview_time_limit' => 25,
            'app.timezone' => 'Asia/Makassar',
        ]);
        $this->bytes = self::xlsx(self::book());

        $this->microsoft = [
            'token' => fn () => Http::response(['token_type' => 'Bearer', 'expires_in' => 3599, 'access_token' => 'token-1']),
            'book' => fn () => Http::response([
                'id' => 'BOOK1', 'name' => 'Daftar Induk.xlsx', 'size' => strlen($this->bytes), 'eTag' => '"{BOOK},' . $this->version . '"',
                'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                'lastModifiedDateTime' => '2026-10-06T07:01:28Z',
                'webUrl' => 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D',
                'parentReference' => ['driveId' => 'b!drive-1'],
            ]),
            'folder' => fn () => Http::response([
                'id' => 'ROOT1', 'name' => 'Scans', 'webUrl' => self::FOLDER_URL, 'folder' => ['childCount' => 5], 'parentReference' => ['driveId' => 'b!drive-1'],
            ]),
            'content' => fn () => Http::response('', 302, ['Location' => self::DOWNLOAD . '?UniqueId=1&tempauth=one-time']),
            'download' => fn () => Http::response($this->bytes),
            'children' => fn (GraphRequest $request) => Http::response(['value' => str_contains($request->url(), 'Lama') ? self::below() : self::files()]),
        ];

        Http::fake(function (GraphRequest $request, array $options) {
            $url = $request->url();
            $this->sentWith[] = [$url, $options];
            $drive = 'https://graph.microsoft.com/v1.0/drives/';

            return match (true) {
                str_starts_with($url, 'https://login.microsoftonline.com/') => $this->microsoft['token']($request, $options),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/shares/') => $this->microsoft[$this->isTheWorkbook($url) ? 'book' : 'folder']($request, $options),
                str_starts_with($url, $drive) && str_ends_with(strtok($url, '?'), '/content') => $this->microsoft['content']($request, $options),
                str_starts_with($url, $drive) => $this->microsoft['children']($request, $options),
                str_starts_with($url, self::DOWNLOAD) => $this->microsoft['download']($request, $options),
                default => Http::response('not Microsoft', 500),
            };
        });
    }

    protected function tearDown(): void
    {
        if ($this->began === null) {
            unset($_SERVER['REQUEST_TIME_FLOAT']);
        } else {
            $_SERVER['REQUEST_TIME_FLOAT'] = $this->began;
        }

        parent::tearDown();
    }

    /** The address in "/shares/u!<base64>/driveItem" is the workbook's or the folder's. */
    private function isTheWorkbook(string $url): bool
    {
        $encoded = substr(explode('/', substr($url, strlen('https://graph.microsoft.com/v1.0/shares/')))[0], 2);

        return str_contains((string) base64_decode(strtr($encoded, '-_', '+/')), 'Doc.aspx');
    }

    private static function link(string $name): string
    {
        return self::FOLDER_URL . '/' . $name;
    }

    /**
     * The COF sheet, by Excel row. 2: its file is there. 3: the file has two spaces in its name where the link has one. 4: no file.
     * 5: the file is there (letter case apart). 6: a "/" in the typed name, so the link goes into a folder. 7: no link, and no file.
     * 8: two spaces in the link as the file has them. 9: no link, but the file is there. 10: the file is there. 11: no file.
     * (So the cards count 4 / 3 / 2 / 1 of 10.) The last two sheets are for the sheet that is asked for by a name that is a number.
     */
    private static function book(): array
    {
        return [
            'SRF' => [
                ['SRF No', 'Requested By', 'Lokasi', 'Unit', 'Keterangan'],
                ['ICT/SRF/1', 'Budi Santoso', null, null, 'unik5'],
            ],
            'COF' => [
                ['No', 'COF No', 'Catatan', 'Tautan'],
                [1, 'ICT/COF/1', 'Menyusul', self::link('ICTCOF1 - Budi Santoso.pdf')],
                [2, 'ICT/COF/2', null, self::link('ICTCOF2 - Andi Wijaya.pdf')],
                [3, 'ICT/COF/3', null, self::link('ICTCOF3 - Citra Dewi.pdf')],
                [4, 'ICT/COF/4', 'Rangkap', self::link('ictcof4 - eka lestari.PDF')],
                [5, 'ICT/COF/5', null, self::link('ICTCOF5 - Fajar/La Tantri.pdf')],
                [6, 'ICT/COF/6', null, null],
                [7, 'ICT/COF/7', null, self::link('ICTCOF7 - Gita  Lestari.pdf')],
                [8, 'ICT/COF/8', null, null],
                [9, 'ICT/COF/10', null, self::link('ICTCOF10 - Joko.pdf')],
                [10, 'ICT/COF/11', null, self::link('ICTCOF11 - Kiki.pdf')],
            ],
            '2025' => [['Kode'], ['A']],
            'Akhir' => [['Kode'], ['B']],
        ];
    }

    private static function file(string $name, string $modified, string $dir = ''): array
    {
        return [
            'id' => strtoupper(md5($name)),
            'name' => $name,
            'size' => 1000,
            'file' => ['mimeType' => 'application/pdf'],
            'lastModifiedDateTime' => $modified,
            'webUrl' => self::FOLDER_URL . $dir . '/' . rawurlencode($name),
        ];
    }

    /** What the folder holds: a folder and seven scans (newest first by their number), two with two spaces, one that no row has. */
    private static function files(): array
    {
        return [
            ['folder' => ['childCount' => 1], 'size' => 0] + array_diff_key(self::file('Lama', '2025-12-01T00:00:00Z'), ['file' => true]),
            self::file('ICTCOF1 - Budi Santoso.pdf', '2026-01-07T00:00:00Z'),
            self::file('ICTCOF2 - Andi  Wijaya.pdf', '2026-01-06T00:00:00Z'),
            self::file('ICTCOF4 - Eka Lestari.pdf', '2026-01-05T00:00:00Z'),
            self::file('ICTCOF7 - Gita  Lestari.pdf', '2026-01-04T00:00:00Z'),
            self::file('ICTCOF8 - Hadi.pdf', '2026-01-03T00:00:00Z'),
            self::file('ICTCOF9 - Tanpa Baris.pdf', '2026-01-02T00:00:00Z'),
            self::file('ICTCOF10 - Joko.pdf', '2026-01-01T00:00:00Z'),
        ];
    }

    /** What is in the folder "Lama": the file that the fifth row's link names (in a folder that the link made up). */
    private static function below(): array
    {
        return [self::file('La Tantri.pdf', '2025-11-01T00:00:00Z', '/Lama')];
    }

    private function rows(array $query = []): TestResponse
    {
        return $this->getJson(route('documents.ict-masterlist.rows', array_merge(['sheet' => 1], $query)));
    }

    private function listing(array $query = []): TestResponse
    {
        return $this->getJson(route('documents.cof-scan.list', $query));
    }

    /** How many requests went to an address containing $part. */
    private function sent(string $part): int
    {
        return Http::recorded(fn (GraphRequest $request) => str_contains($request->url(), $part))->count();
    }

    /** The time limit of the first request that went to an address containing $part. */
    private function timeoutOf(string $part): float
    {
        $first = collect($this->sentWith)->first(fn (array $sent) => str_contains(strtok($sent[0], '?'), $part));

        return (float) $first[1]['timeout'];
    }

    /** Ticks a menu for a role, with Read (or without it). */
    private function grant(string $role, string $route, bool $read = true): void
    {
        $grant = [
            'menu_id' => DB::table('menu')->where('route', $route)->value('id'),
            'role_id' => DB::table('roles')->where('name', $role)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('menu_role')->insert($grant);
        if ($read) {
            DB::table('menu_role_permission')->insert($grant + ['permission_id' => CheckMenuPermission::PERMISSION_IDS['Read']]);
        }
    }

    /** What the rows of the COF sheet say, by Excel row. */
    private function texts(TestResponse $response): array
    {
        return collect($response->json('data'))->mapWithKeys(fn (array $row) => [$row['line'] => end($row['cells'])])->all();
    }

    /** The Excel rows of a page of the sheet, in the order they are shown. */
    private function linesOf(TestResponse $response): array
    {
        return array_column($response->json('data'), 'line');
    }

    /** The names of the files of a page of the folder, in the order they are shown. */
    private function namesOf(TestResponse $response): array
    {
        return array_column($response->json('data'), 'name');
    }

    // ================================================================ the page of the workbook

    public function test_the_sheet_says_for_every_row_whether_its_file_is_in_the_folder(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $response = $this->rows()->assertOk()
            ->assertJsonPath('sheet.name', 'COF')
            ->assertJsonPath('sheet.columns', ['No', 'COF No', 'Catatan', 'Tautan', 'File scan']);

        // Last row first, as the workbook is shown; the status is the last cell.
        $this->assertSame(
            [11 => 'Belum ada', 10 => 'Ada', 9 => 'Nama beda', 8 => 'Ada', 7 => 'Belum ada', 6 => 'Subfolder', 5 => 'Ada', 4 => 'Belum ada', 3 => 'Nama beda', 2 => 'Ada'],
            $this->texts($response)
        );
        // Each row also says its status, and which file it goes to on the page of the folder (the one its link names, else the one with its number).
        $this->assertSame(
            [
                ['status' => 'missing', 'code' => ''], ['status' => 'ok', 'code' => 'ICTCOF10'], ['status' => 'differs', 'code' => 'ICTCOF8'],
                ['status' => 'ok', 'code' => 'ICTCOF7'], ['status' => 'missing', 'code' => ''], ['status' => 'below', 'code' => ''],
                ['status' => 'ok', 'code' => 'ICTCOF4'], ['status' => 'missing', 'code' => ''], ['status' => 'differs', 'code' => 'ICTCOF2'],
                ['status' => 'ok', 'code' => 'ICTCOF1'],
            ],
            array_column($response->json('data'), 'scan')
        );

        // The counts for the cards, of the whole sheet; how old the folder's listing is; where its page is.
        $response->assertJsonPath('sheet.scan.column', 4)
            ->assertJsonPath('sheet.scan.counts', ['total' => 10, 'ok' => 4, 'missing' => 3, 'differs' => 2, 'below' => 1])
            ->assertJsonPath('sheet.scan.status', null)
            ->assertJsonPath('sheet.scan.fetched_at', '07-10-2026 10:15')
            ->assertJsonPath('sheet.scan.stale', false)
            ->assertJsonPath('sheet.scan.folder', 'COF Scan')
            ->assertJsonPath('sheet.scan.open', '/documents/cof-scan')
            ->assertJsonMissingPath('sheet.lines');

        // The other sheets are as they were: no column, no counts, no status in their rows.
        $this->rows(['sheet' => 0])->assertOk()
            ->assertJsonPath('sheet.columns', ['SRF No', 'Requested By', 'Lokasi', 'Unit', 'Keterangan'])
            ->assertJsonMissingPath('sheet.scan')
            ->assertJsonMissingPath('data.0.scan');

        // What was read is not changed by being checked: the same answer the second time, the same single column.
        $this->rows()->assertOk()->assertJsonPath('sheet.columns', ['No', 'COF No', 'Catatan', 'Tautan', 'File scan']);
        // A second look is made from what is kept: the folder was asked once, and so was the workbook's file.
        $this->assertSame(1, $this->sent('/children'));
        $this->assertSame(1, $this->sent('download.aspx'));
    }

    public function test_a_link_with_two_spaces_finds_the_file_that_has_two_and_one_with_one_does_not(): void
    {
        $this->actingAs($this->superAdmin());

        // Line 8 has two spaces in its link, as its file has (the reader keeps them as they were typed); line 3 has one, and its file two.
        $response = $this->rows(['line' => '3,8'])->assertOk();

        $this->assertSame([8 => 'Ada', 3 => 'Nama beda'], $this->texts($response));
        $this->assertStringEndsWith('/ICTCOF7 - Gita  Lestari.pdf', $response->json('data.0.cells.3'), 'the link is shown as it was typed');
    }

    public function test_the_sheet_can_be_asked_for_by_its_name_and_the_card_that_was_clicked_shows_its_rows(): void
    {
        $this->actingAs($this->superAdmin());
        $by = fn (array $query) => $this->getJson(route('documents.ict-masterlist.rows', $query))->assertOk();

        $by(['sheet' => 'cof'])->assertJsonPath('sheet.index', 1)->assertJsonPath('sheet.name', 'COF');
        $by(['sheet' => ' COF '])->assertJsonPath('sheet.index', 1, 'spaces around it are trimmed like those of any other input');
        $by(['sheet' => 'Tidak ada'])->assertJsonPath('sheet.index', 0);
        $by(['sheet' => '1'])->assertJsonPath('sheet.index', 1);
        $by(['sheet' => '2025'])->assertJsonPath('sheet.index', 2, 'a sheet that is called by a number is found by that name, not taken for the 2025th');
        $by(['sheet' => '3'])->assertJsonPath('sheet.index', 3);
        $by(['sheet' => '99'])->assertJsonPath('sheet.index', 3);
        $by(['sheet' => '-5'])->assertJsonPath('sheet.index', 0);

        foreach (['ok' => [10, 8, 5, 2], 'missing' => [11, 7, 4], 'differs' => [9, 3], 'below' => [6]] as $status => $lines) {
            $response = $this->rows(['status' => $status])->assertOk();
            $this->assertSame($lines, $this->linesOf($response), $status);
            $response->assertJsonPath('meta.total', count($lines))
                ->assertJsonPath('sheet.scan.status', $status)
                // The cards keep counting the whole sheet.
                ->assertJsonPath('sheet.scan.counts.total', 10)
                ->assertJsonPath('sheet.scan.counts.ok', 4);
        }

        // Anything else is no card: all the rows. So is a card on a sheet that has none.
        $this->rows(['status' => 'bogus'])->assertOk()->assertJsonPath('meta.total', 10)->assertJsonPath('sheet.scan.status', null);
        $this->rows(['status' => ''])->assertOk()->assertJsonPath('meta.total', 10);
        $this->rows(['status' => ['ok']])->assertOk()->assertJsonPath('meta.total', 10);
        $this->rows(['sheet' => 0, 'status' => 'ok'])->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_the_tabs_ask_by_position_and_a_sheet_that_is_called_by_a_number_is_found_by_its_name(): void
    {
        $this->actingAs($this->superAdmin());
        // A sheet called "1" is the third: the second tab must still open the second sheet, and the first tab the first.
        $this->bytes = self::xlsx(['SRF' => [['Kode'], ['A']], 'COF' => self::book()['COF'], '1' => [['Kode'], ['B']]]);
        $at = fn (array $query) => $this->getJson(route('documents.ict-masterlist.rows', $query))->assertOk();

        $at(['tab' => 1])->assertJsonPath('sheet.index', 1)->assertJsonPath('sheet.name', 'COF')->assertJsonPath('sheet.scan.counts.total', 10);
        $at(['tab' => 2])->assertJsonPath('sheet.index', 2)->assertJsonPath('sheet.name', '1');
        $at(['tab' => 0])->assertJsonPath('sheet.index', 0);
        // A link asks by name: the sheet that is called "1" is found by that, before the second is taken for it.
        $at(['sheet' => '1'])->assertJsonPath('sheet.index', 2);
        $at(['sheet' => 'cof'])->assertJsonPath('sheet.index', 1);
        // A position wins over a name; it is a position only when it is a whole number of a few digits.
        $at(['tab' => 1, 'sheet' => '1'])->assertJsonPath('sheet.index', 1);
        $at(['tab' => 9999])->assertJsonPath('sheet.index', 2, 'the last sheet there is');
        foreach (['x', '-1', '1.5', '', ' ', '12345', '0x1'] as $odd) {
            $at(['tab' => $odd, 'sheet' => 'COF'])->assertJsonPath('sheet.index', 1);
        }
        $at(['tab' => ['1'], 'sheet' => 'COF'])->assertJsonPath('sheet.index', 1);
        $at(['tab' => '12345'])->assertJsonPath('sheet.index', 0, 'not a position, and no name either');
    }

    public function test_a_link_from_the_page_of_the_folder_asks_for_rows_of_excel(): void
    {
        $this->actingAs($this->superAdmin());

        $one = $this->rows(['line' => '3'])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('sheet.lines', [3]);
        $this->assertSame([3], $this->linesOf($one));
        // Several, last row first as always; what was asked for is told back.
        $two = $this->rows(['line' => '3,8'])->assertOk()->assertJsonPath('sheet.lines', [3, 8]);
        $this->assertSame([8, 3], $this->linesOf($two));
        $this->assertSame([3], $this->linesOf($this->rows(['line' => '3, 3 ,3'])), 'once, whichever way it is written');
        // The cards go on counting the whole sheet; the other filters still count.
        $this->rows(['line' => '3'])->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertSame([2], $this->linesOf($this->rows(['line' => '2,3,4', 'status' => 'ok'])));
        $this->assertSame([4], $this->linesOf($this->rows(['line' => '2,3,4', 'search' => 'ICTCOF3'])));
        $this->assertSame([], $this->linesOf($this->rows(['line' => '4', 'status' => 'ok'])));
        // Rows that are not there: nothing, and it is said which rows.
        $this->rows(['line' => '500'])->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('sheet.lines', [500]);
        // Whatever is not a row of Excel is no row: all the rows, and nothing is said.
        foreach (['abc', '0', '-3', '1.5', '', ' , ', '1234567890', '99999999999'] as $text) {
            $this->rows(['line' => $text])->assertOk()->assertJsonPath('meta.total', 10)->assertJsonMissingPath('sheet.lines');
        }
        $this->assertSame([3], $this->linesOf($this->rows(['line' => '0,-1,3,x'])));
        $this->assertSame([8, 3], $this->linesOf($this->rows(['line' => '3 8'])), 'blanks split the rows as commas do');
        $this->rows(['line' => '123456789'])->assertJsonPath('sheet.lines', [123456789]);
        $this->rows(['line' => ['3']])->assertOk()->assertJsonPath('meta.total', 10);
        // No more than 20 rows are asked for at a time (the others are left out): rows 2 and 11 are among the first 20 numbers, row 22 would not be.
        $many = $this->rows(['line' => implode(',', range(2, 41))])->assertOk();
        $this->assertSame(range(2, 21), $many->json('sheet.lines'));
        $this->assertSame(range(11, 2), $this->linesOf($many));
        // Any sheet can be asked for rows, not only the one that is checked.
        $this->rows(['sheet' => 0, 'line' => '2'])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('sheet.lines', [2]);
    }

    public function test_the_status_is_sorted_like_any_other_column_and_is_not_searched(): void
    {
        $this->actingAs($this->superAdmin());

        // The words of the column are not looked for: "ada" would find every row, and "nama" the rows whose file has another name.
        foreach (['belum', 'ada', 'nama beda', 'subfolder', 'Belum ada'] as $word) {
            $this->rows(['search' => $word])->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('sheets.1.matches', 0);
        }
        // Every other column is: a name finds its row, and the tab counts the rows that match.
        $response = $this->rows(['search' => 'rangkap'])->assertOk();
        $this->assertSame([5], $this->linesOf($response));
        $response->assertJsonPath('sheets.1.matches', 1)->assertJsonPath('sheets.0.matches', 0);
        // The column that is left out is the one of the sheet on screen: the other sheets are looked through in all their columns.
        $this->rows(['search' => 'unik5'])->assertOk()->assertJsonPath('sheets.0.matches', 1)->assertJsonPath('sheets.1.matches', 0)->assertJsonPath('meta.total', 0);
        $this->rows(['sheet' => 0, 'search' => 'unik5'])->assertOk()->assertJsonPath('meta.total', 1);
        // With a card: both count.
        $this->assertSame([4], $this->linesOf($this->rows(['search' => 'ICTCOF3', 'status' => 'missing'])));
        $this->assertSame([], $this->linesOf($this->rows(['search' => 'ICTCOF3', 'status' => 'ok'])));
        // The cards go on counting the whole sheet, whatever is searched for.
        $this->rows(['search' => 'rangkap'])->assertJsonPath('sheet.scan.counts.total', 10);

        // By the column: Ada, Belum ada, Nama beda, Subfolder; rows of one kind stay in the order of the workbook, whichever way it goes.
        $this->assertSame([2, 5, 8, 10, 4, 7, 11, 3, 9, 6], $this->linesOf($this->rows(['sort' => 'c4', 'dir' => 'asc'])));
        $this->assertSame([6, 3, 9, 4, 7, 11, 2, 5, 8, 10], $this->linesOf($this->rows(['sort' => 'c4', 'dir' => 'desc'])));
        $this->rows(['sort' => 'c4'])->assertJsonPath('sheet.sort', 'c4');
        // The column after the last is no column.
        $this->rows(['sort' => 'c5'])->assertJsonPath('sheet.sort', 'row');
    }

    public function test_a_page_of_rows_is_told_the_same_whatever_its_size(): void
    {
        $this->actingAs($this->superAdmin());

        $second = $this->rows(['paginated' => 2, 'page' => 2])->assertOk();

        $this->assertSame([9, 8], $this->linesOf($second));
        $this->assertSame(['Nama beda', 'Ada'], array_map(fn (array $row) => end($row['cells']), $second->json('data')));
        $second->assertJsonPath('data.0.scan.status', 'differs')->assertJsonPath('data.1.scan.status', 'ok')->assertJsonPath('meta.total', 10);
    }

    public function test_only_the_sheet_on_screen_is_checked_against_the_folder(): void
    {
        $this->actingAs($this->superAdmin());

        // Another sheet is asked for: nothing to check, so nothing is asked of the folder.
        $this->rows(['sheet' => 0])->assertOk()->assertJsonMissingPath('sheet.scan');
        $this->rows(['sheet' => '2025'])->assertOk()->assertJsonMissingPath('sheet.scan');
        $this->rows(['sheet' => 0, 'search' => 'budi'])->assertOk();
        $this->assertSame(0, $this->sent('/children'));
        $this->assertSame(1, $this->sent('/shares/'), 'only the workbook was looked up');

        // The COF sheet is on screen: now the folder is read.
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertSame(1, $this->sent('/children'));
    }

    public function test_without_the_folder_the_sheet_is_shown_as_it_is_and_says_why(): void
    {
        $this->actingAs($this->superAdmin());
        $columns = ['No', 'COF No', 'Catatan', 'Tautan'];

        // Microsoft cannot be reached for the folder (the workbook can).
        $this->microsoft['children'] = fn () => Http::response(['error' => ['code' => 'generalException', 'message' => 'Microsoft says sorry (token-1)']], 503);
        $response = $this->rows()->assertOk()->assertJsonPath('sheet.columns', $columns)->assertJsonPath('meta.total', 10);
        $response->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.')
            ->assertJsonMissingPath('sheet.scan.counts')
            ->assertJsonMissingPath('data.0.scan');
        $this->assertStringNotContainsString('token-1', (string) $response->getContent(), 'what Microsoft said stays out of the page');
        // ...and the status filter is no filter without a status.
        $this->rows(['status' => 'ok'])->assertOk()->assertJsonPath('meta.total', 10);

        // The sheet has no column of that title: the owner is told which.
        $this->microsoft['children'] = fn (GraphRequest $request) => Http::response(['value' => self::files()]);
        config(['sharepoint-docs.workbooks.ict-masterlist.scans.COF.link' => 'Alamat']);
        Cache::flush();
        $this->rows()->assertOk()->assertJsonPath('sheet.columns', $columns)
            ->assertJsonPath('sheet.scan.unavailable', 'Kolom "Alamat" tidak ditemukan di sheet COF, jadi status file scan tidak bisa dihitung.');

        // A sheet that the workbook does not have, a folder that is not set up and a folder that is not in the settings: nothing to check, nothing said.
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => ['Tidak Ada' => ['folder' => 'cof-scan', 'link' => 'Tautan']]]);
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['folder' => 'cof-scan', 'link' => 'Tautan']], 'sharepoint-docs.folders.cof-scan.url' => '']);
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan')->assertJsonPath('sheet.columns', $columns);
        config(['sharepoint-docs.folders.cof-scan.url' => self::FOLDER_ADDRESS, 'sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['folder' => 'tidak-ada', 'link' => 'Tautan']]]);
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        // A setting without its folder, or without the title of the column of links, is no setting.
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['link' => 'Tautan'], 'Akhir' => ['folder' => 'cof-scan'], '2025' => 'cof-scan']]);
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        $this->rows(['sheet' => 'Akhir'])->assertOk()->assertJsonMissingPath('sheet.scan');
    }

    public function test_a_folder_that_is_too_large_to_be_read_whole_is_not_counted_against(): void
    {
        $this->actingAs($this->superAdmin());

        // The listing goes on and on: what is counted would not be the folder.
        $this->microsoft['children'] = fn () => Http::response([
            'value' => self::files(),
            '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/drives/b!drive-1/items/ROOT1/children?$skiptoken=more',
        ]);

        $this->rows()->assertOk()
            ->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan: isi folder COF Scan terlalu banyak untuk dibaca seluruhnya, jadi status filenya tidak bisa dihitung.')
            ->assertJsonPath('sheet.columns', ['No', 'COF No', 'Catatan', 'Tautan'])
            ->assertJsonMissingPath('sheet.scan.counts');
    }

    public function test_the_folder_that_was_kept_is_used_when_microsoft_cannot_be_reached_and_it_says_so(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.stale', false);

        // After the minutes it is kept for, Microsoft is asked again and cannot be reached: the kept listing is used.
        $this->travel(11)->minutes();
        $this->microsoft['children'] = fn () => Http::response('', 503);
        $response = $this->rows()->assertOk();

        $response->assertJsonPath('sheet.scan.stale', true)
            ->assertJsonPath('sheet.scan.fetched_at', '07-10-2026 10:15')
            ->assertJsonPath('sheet.scan.counts.ok', 4);
    }

    public function test_the_workbook_that_was_kept_is_used_for_the_folder_when_microsoft_cannot_be_reached_and_it_says_so(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->listing()->assertOk()->assertJsonPath('register.stale', false);

        // The listing is read again after the minutes it is kept for (the folder is the first source and answers), the workbook is too old
        // and cannot be reached: its kept copy is used for the columns, and the page says it is old.
        $this->travel(11)->minutes();
        $this->microsoft['book'] = fn () => Http::response('', 503);
        $this->listing()->assertOk()
            ->assertJsonPath('register.stale', true)
            ->assertJsonPath('register.fetched_at', '07-10-2026 10:15')
            ->assertJsonPath('data.1.masterlist.status', 'linked');
    }

    public function test_the_settings_are_read_as_they_are_written(): void
    {
        $this->actingAs($this->superAdmin());

        // The title of the sheet in the setting: letter case and blanks at its ends do not matter (as the title of a column).
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => [' cOf ' => ['folder' => 'cof-scan', 'link' => ' tautan ', 'code' => 'cof NO', 'note' => 'CATATAN']]]);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts', ['total' => 10, 'ok' => 4, 'missing' => 3, 'differs' => 2, 'below' => 1]);
        $this->assertSame('Menyusul', $this->listing()->json('data.1.note'));

        // What separates the number from the rest of a file's name is the folder's own setting: with another one no file has a number.
        config(['sharepoint-docs.folders.cof-scan.name_split.separator' => ' ~ ']);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts', ['total' => 10, 'ok' => 4, 'missing' => 5, 'differs' => 0, 'below' => 1]);
        $this->assertSame('unlinked', $this->listing()->json('data.5.masterlist.status'));
        config(['sharepoint-docs.folders.cof-scan.name_split.separator' => ' - ']);

        // Without a setting for it the separator is the one the names of the files have.
        config(['sharepoint-docs.folders.cof-scan.name_split' => null]);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts', ['total' => 10, 'ok' => 4, 'missing' => 3, 'differs' => 2, 'below' => 1]);
        config(['sharepoint-docs.folders.cof-scan.name_split' => ['separator' => ' - ', 'code' => 'No. COF', 'label' => 'Nama']]);

        // The first sheet that is tied to the folder is the one that lists its files.
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => [
            'COF' => ['folder' => 'cof-scan', 'link' => 'Tautan', 'code' => 'COF No', 'note' => 'Catatan'],
            'Akhir' => ['folder' => 'cof-scan', 'link' => 'Kode'],
        ]]);
        $this->listing()->assertOk()->assertJsonPath('register.sheet', 'COF')->assertJsonPath('data.1.masterlist.status', 'linked');
        // Both sheets are checked against it, one at a time, whichever is on screen.
        $this->rows(['sheet' => 'Akhir'])->assertOk()->assertJsonPath('sheet.scan.counts.total', 1);
    }

    public function test_the_sheet_that_lists_a_folder_is_found_by_the_folder_and_no_other_folder_has_one(): void
    {
        config(['sharepoint-docs.folders.andere' => ['title' => 'Lain', 'url' => self::FOLDER_ADDRESS]]);

        $this->assertSame(
            ['workbook' => 'ict-masterlist', 'sheet' => 'COF', 'folder' => 'cof-scan', 'link' => 'Tautan', 'code' => 'COF No', 'note' => 'Catatan'],
            ScanLinks::ofFolder('cof-scan')
        );
        $this->assertNull(ScanLinks::ofFolder('andere'), 'a folder that no sheet is tied to');
        $this->assertNull(ScanLinks::ofFolder(''));
        $this->assertSame(['COF'], array_keys(ScanLinks::ofWorkbook('ict-masterlist')));
        $this->assertSame([], ScanLinks::ofWorkbook('tidak-ada'));
    }

    public function test_a_folder_that_has_the_name_of_a_file_is_no_file(): void
    {
        $this->actingAs($this->superAdmin());
        // A folder called like the file that row 4 names does not have that file in it.
        $this->microsoft['children'] = fn () => Http::response(['value' => array_merge(self::files(), [
            ['folder' => ['childCount' => 0], 'size' => 0] + array_diff_key(self::file('ICTCOF3 - Citra Dewi.pdf', '2025-12-01T00:00:00Z'), ['file' => true]),
        ])]);

        $this->assertSame([11, 7, 4], $this->linesOf($this->rows(['status' => 'missing'])));
        $this->listing()->assertOk()->assertJsonPath('folder.files', 7)->assertJsonPath('folder.folders', 2);
    }

    public function test_a_kept_listing_and_a_kept_workbook_are_at_hand_for_the_minutes_they_are_kept_and_the_seconds_after_muat_ulang(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $folders = $this->app->make(SharePointFolder::class);
        $books = $this->app->make(SharePointWorkbook::class);
        $this->assertFalse($folders->fresh('cof-scan'), 'nothing is kept yet');
        $this->assertFalse($books->fresh('ict-masterlist'));
        $this->rows()->assertOk();

        // [seconds after the reading, "Muat ulang", at hand]: 10 minutes are kept, 15 seconds are all "Muat ulang" accepts.
        foreach ([[0, false, true], [0, true, true], [14, true, true], [15, true, false], [16, true, false], [599, false, true], [600, false, false], [601, false, false]] as [$seconds, $refresh, $fresh]) {
            $this->travelTo(Carbon::parse(self::NOW, 'UTC')->addSeconds($seconds));
            $this->assertSame($fresh, $folders->fresh('cof-scan', '', $refresh), "the listing after {$seconds} seconds" . ($refresh ? ' (Muat ulang)' : ''));
            $this->assertSame($fresh, $books->fresh('ict-masterlist', $refresh), "the workbook after {$seconds} seconds" . ($refresh ? ' (Muat ulang)' : ''));
        }
        // A copy that is dated in the future (the clock was set back) is not at hand.
        foreach ([false, true] as $refresh) {
            $this->travelTo(Carbon::parse(self::NOW, 'UTC')->subSeconds(5));
            $this->assertFalse($folders->fresh('cof-scan', '', $refresh));
            $this->assertFalse($books->fresh('ict-masterlist', $refresh));
        }
        // A folder below has a listing of its own.
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->assertFalse($folders->fresh('cof-scan', 'Lama'));
        // Nothing of what is not set up.
        config(['sharepoint-docs.folders.cof-scan.url' => '', 'sharepoint-docs.workbooks.ict-masterlist.url' => '']);
        $this->assertFalse($folders->fresh('cof-scan'));
        $this->assertFalse($books->fresh('ict-masterlist'));
    }

    public function test_a_workbook_that_was_kept_by_another_version_or_for_another_choice_of_sheets_is_not_at_hand(): void
    {
        $books = $this->app->make(SharePointWorkbook::class);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $address = self::BOOK_ADDRESS;
        $shown = (new \ReflectionMethod($books, 'shown'))->invoke($books, 'ict-masterlist');
        $key = fn (string $choice) => (new \ReflectionMethod($books, 'cacheKey'))->invoke($books, 'book', $address . "\n" . $choice);
        $kept = fn (int $format) => ['format' => $format, 'fetched_at' => now()->getTimestamp(), 'sheets' => []];

        // The copy that this version makes, for this choice of sheets, is at hand...
        Cache::put($key($shown), $kept(4), 3600);
        $this->assertTrue($books->fresh('ict-masterlist'));
        // ...one that another version made is not...
        Cache::put($key($shown), $kept(2), 3600);
        $this->assertFalse($books->fresh('ict-masterlist'));
        // ...nor is one without its time or its sheets...
        Cache::put($key($shown), ['format' => 4, 'sheets' => []], 3600);
        $this->assertFalse($books->fresh('ict-masterlist'));
        Cache::put($key($shown), ['format' => 4, 'fetched_at' => now()->getTimestamp()], 3600);
        $this->assertFalse($books->fresh('ict-masterlist'));
        // ...nor is one that was kept for another choice of sheets.
        Cache::forget($key($shown));
        Cache::put($key("another choice"), $kept(4), 3600);
        $this->assertFalse($books->fresh('ict-masterlist'));
    }

    // ================================================================ who may see what

    public function test_a_reader_gets_the_other_source_only_where_they_may_open_its_page(): void
    {
        // Viewer Radio is given neither page by default.
        $viewer = $this->makeUser('viewer.radio@test.local', 'Viewer Radio');
        $this->grant('Viewer Radio', IctMasterlistMenuSeeder::ROUTE);

        // May read the workbook, not the folder: no column, and the folder is not asked for.
        $this->actingAs($viewer->fresh());
        $this->rows()->assertOk()->assertJsonPath('sheet.columns', ['No', 'COF No', 'Catatan', 'Tautan'])->assertJsonMissingPath('sheet.scan');
        $this->assertSame(0, $this->sent('/children'));
        $this->assertSame(1, $this->sent('/shares/'), 'only the workbook was looked up');

        // Ticked for the folder too: the column comes.
        $this->grant('Viewer Radio', CofScanMenuSeeder::ROUTE);
        $this->actingAs($viewer->fresh());
        $this->rows()->assertOk()->assertJsonPath('sheet.columns.4', 'File scan')->assertJsonPath('sheet.scan.counts.total', 10);

        // Admin Radio has both pages by default.
        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio');
        $this->actingAs($admin);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.ok', 4);
        $this->listing()->assertOk()->assertJsonPath('register.sheet', 'COF');
    }

    public function test_a_menu_that_is_ticked_without_read_gives_nothing_of_the_other_page(): void
    {
        // May read the workbook; the folder's menu is ticked for the role but Read is not: its own page is closed to them, and so is what it knows.
        $viewer = $this->makeUser('viewer.radio@test.local', 'Viewer Radio');
        $this->grant('Viewer Radio', IctMasterlistMenuSeeder::ROUTE);
        $this->grant('Viewer Radio', CofScanMenuSeeder::ROUTE, false);
        $this->actingAs($viewer->fresh());

        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        $this->assertSame(0, $this->sent('/children'));
        $this->listing()->assertForbidden();

        // The other way round: may read the folder; the workbook's menu is ticked without Read.
        $other = $this->makeUser('other.radio@test.local', 'Admin Radio');
        DB::table('menu_role_permission')->where('role_id', $other->roles->first()->id)
            ->where('menu_id', DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->value('id'))->delete();
        $this->actingAs($other->fresh());
        Cache::flush();
        $workbookReads = $this->sent('/content');
        $this->listing()->assertOk()->assertJsonMissingPath('register')->assertJsonMissingPath('data.1.masterlist')->assertJsonMissingPath('data.1.note');
        $this->assertSame($workbookReads, $this->sent('/content'), 'the workbook was not asked for');
        $this->rows()->assertForbidden();
    }

    public function test_a_read_that_is_not_ticked_for_the_menu_gives_nothing_of_the_other_page(): void
    {
        $viewer = $this->makeUser('viewer.radio@test.local', 'Viewer Radio');
        $this->grant('Viewer Radio', IctMasterlistMenuSeeder::ROUTE);
        // Read for the folder's menu, but the menu itself is not ticked for the role.
        DB::table('menu_role_permission')->insert([
            'menu_id' => DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->value('id'),
            'role_id' => DB::table('roles')->where('name', 'Viewer Radio')->value('id'),
            'permission_id' => CheckMenuPermission::PERMISSION_IDS['Read'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($viewer->fresh());

        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        $this->assertSame(0, $this->sent('/children'));
    }

    public function test_a_page_without_a_menu_row_is_closed_to_everyone_but_super_admin(): void
    {
        $folderMenu = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->value('id');
        DB::table('menu_role_permission')->where('menu_id', $folderMenu)->delete();
        DB::table('menu_role')->where('menu_id', $folderMenu)->delete();
        DB::table('menu')->where('id', $folderMenu)->delete();
        Cache::forget('rbac_menu_route_key_map');

        // Admin Radio has the workbook's menu: the page opens, without what the folder knows.
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'));
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan');
        $this->assertSame(0, $this->sent('/children'));

        // Super Admin passes every menu.
        $this->actingAs($this->superAdmin());
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
    }

    // ================================================================ the time of a request

    public function test_the_second_source_is_asked_for_on_the_time_that_is_left(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // The workbook takes 20 of the 27 seconds a request has for both: the folder gets the 7 that are left, not its 20.
        $this->microsoft['download'] = $this->after(20, fn () => Http::response($this->bytes));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);

        $this->assertEqualsWithDelta(7.0, $this->timeoutOf('/children'), 0.01);
    }

    public function test_the_time_of_a_request_counts_from_when_the_server_began_it(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // The framework took 2 seconds before the controller ran: of the 27 seconds, 20 for the workbook, 5 are left for the folder.
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 2.0;
        $this->microsoft['download'] = $this->after(20, fn () => Http::response($this->bytes));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        // (Less than 5 by the little time that the test itself took between setting the moment and the controller; not 7: the 2 seconds count.)
        $this->assertLessThanOrEqual(5.0, $this->timeoutOf('/children'));
        $this->assertGreaterThanOrEqual(3.0, $this->timeoutOf('/children'));

        // Nearly ten seconds count too: nothing is left for the folder, and it is not asked for.
        Cache::flush();
        $this->sentWith = [];
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 8.5;
        $asked = $this->sent('/children');
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.');
        $this->assertSame($asked, $this->sent('/children'));

        // A request that is said to have begun long ago (a worker that stays up) is counted from the controller on.
        Cache::flush();
        $this->sentWith = [];
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 30.0;
        $this->rows()->assertOk();
        $this->assertEqualsWithDelta(7.0, $this->timeoutOf('/children'), 0.01);
        // So is one that began a little more than the 10 seconds that are believed.
        Cache::flush();
        $this->sentWith = [];
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 10.5;
        $this->rows()->assertOk();
        $this->assertEqualsWithDelta(7.0, $this->timeoutOf('/children'), 0.01);
        // So is one that is said to begin in the future.
        Cache::flush();
        $this->sentWith = [];
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) + 30.0;
        $this->rows()->assertOk();
        $this->assertEqualsWithDelta(7.0, $this->timeoutOf('/children'), 0.01);
    }

    public function test_the_second_source_is_asked_for_when_exactly_the_least_time_is_left_and_not_when_less(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // 3 seconds are left of the 27: just enough, and the folder gets all of them.
        $this->microsoft['download'] = $this->after(24.0, fn () => Http::response($this->bytes));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertEqualsWithDelta(3.0, $this->timeoutOf('/children'), 0.01);

        // A thousandth of a second less: not worth a request.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = $this->after(24.001, fn () => Http::response($this->bytes));
        $before = $this->sent('/children');
        $this->rows()->assertOk()->assertJsonMissingPath('sheet.scan.counts');
        $this->assertSame($before, $this->sent('/children'));
    }

    public function test_when_the_first_source_used_up_the_time_the_second_is_not_asked_for_and_the_page_says_so(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // 24.5 seconds: the workbook is read in the 25 it has, and 2.5 are left of the 27: not worth a request.
        $this->microsoft['download'] = $this->after(24.5, fn () => Http::response($this->bytes));
        $this->rows()->assertOk()
            ->assertJsonPath('sheet.columns', ['No', 'COF No', 'Catatan', 'Tautan'])
            ->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.');
        $this->assertSame(0, $this->sent('/children'));
        $this->assertSame(1, $this->sent('/shares/'), 'only the workbook was looked up');
    }

    public function test_a_second_source_that_is_at_hand_needs_no_time(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        // The folder was read a moment ago (its page was open, without the workbook).
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->listing()->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $this->assertSame(1, $this->sent('/children'));
        $this->assertSame(0, $this->sent('download.aspx'));

        // The workbook takes nearly all the time; what the folder knows is at hand, and costs none.
        $this->microsoft['download'] = $this->after(24.5, fn () => Http::response($this->bytes));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertSame(1, $this->sent('/children'), 'the folder was not asked again');
    }

    public function test_the_workbook_as_the_second_source_keeps_the_time_it_needs_to_be_read(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // The folder takes 15 of the 27 seconds. The workbook needs 5 of what is left after its download, to be read: its download gets 7.
        $this->microsoft['children'] = $this->after(15, fn () => Http::response(['value' => self::files()]));
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->assertEqualsWithDelta(7.0, $this->timeoutOf('/shares/' . $this->workbookShare()), 0.01);
    }

    public function test_when_the_folder_used_up_the_time_the_workbook_is_not_asked_for_and_the_page_says_so(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // 19.5 seconds: the folder is read in the 20 it has; 7.5 are left, of which 5 are for reading the workbook: 2.5 are too few for its download.
        $this->microsoft['children'] = $this->after(19.5, fn () => Http::response(['value' => self::files()]));
        $this->listing()->assertOk()
            ->assertJsonPath('meta.total', 8)
            ->assertJsonPath('register.unavailable', 'Kolom Masterlist dan Catatan belum bisa ditampilkan. Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.')
            ->assertJsonMissingPath('data.1.masterlist');
        $this->assertSame(0, $this->sent('/content'));
        $this->assertSame(0, $this->sent('download.aspx'));

        // A little less used, and there is enough: 27 - 18.9 - 5 = 3.1 seconds for the download, and 3 are the least.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['children'] = $this->after(18.9, fn () => Http::response(['value' => self::files()]));
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->assertEqualsWithDelta(3.1, $this->timeoutOf('/shares/' . $this->workbookShare()), 0.01);
    }

    public function test_a_second_source_that_ran_out_of_the_time_it_was_given_holds_nothing_back_for_its_own_page(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // The workbook takes 19 seconds, so the folder is given 8, and runs out of them (a request that is made shorter than usual gives up).
        $this->microsoft['download'] = $this->after(19, fn () => Http::response($this->bytes));
        $this->microsoft['children'] = self::timeout();
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->assertSame(1, $this->sent('/children'), 'the folder was asked, and ran out of the time');

        // Nor is it held back for the next request of the page of the workbook, which has all its time.
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertSame(2, $this->sent('/children'), 'it was asked again at once');
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = $this->after(19, fn () => Http::response($this->bytes));
        $this->microsoft['children'] = self::timeout();
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');

        // The folder's own page has its 20 seconds and is not held back for what happened on a shorter time.
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);
        $this->listing()->assertOk()->assertJsonPath('folder.stale', false)->assertJsonPath('meta.total', 8);

        // The same the other way round: the workbook is given what is left of the time after the folder and runs out of it.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['children'] = $this->after(12, fn () => Http::response(['value' => self::files()]));
        $this->microsoft['download'] = self::timeout();
        $downloads = $this->sent('download.aspx');
        $this->listing()->assertOk()->assertJsonPath(
            'register.unavailable',
            'Kolom Masterlist dan Catatan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.'
        );
        $this->assertSame($downloads + 1, $this->sent('download.aspx'), 'the workbook was asked for, and ran out of the time');
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false)->assertJsonPath('sheet.scan.counts.total', 10);

        // A download that was too slow for the time that was left is no reason either: the page of the workbook is not held back, and the
        // page of the folder asks for the workbook again at its next request.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['children'] = $this->after(12, fn () => Http::response(['value' => self::files()]));
        $this->microsoft['download'] = self::transfer(500_000, 100_000, self::timeout());
        $this->assertStringStartsWith('Kolom Masterlist dan Catatan belum bisa ditampilkan. ', $this->listing()->assertOk()->json('register.unavailable'));
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');
        Cache::flush();
        $this->microsoft['download'] = self::transfer(500_000, 100_000, self::timeout());
        $this->listing()->assertOk();
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false)->assertJsonPath('sheet.scan.counts.total', 10);
    }

    public function test_a_second_source_that_used_its_whole_own_time_is_not_asked_again_for_a_minute(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        // The workbook is read and kept; the folder is not.
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->rows()->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);

        // The workbook is at hand, so the folder has all of the 20 seconds that it has on its own page. Its lookup takes all of them: the
        // listing is not asked for any more (the time is gone), and that is the folder's own trouble, whatever else the request has time for.
        $ok = $this->microsoft['folder'];
        $this->microsoft['folder'] = $this->after(20, $ok);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->assertSame(0, $this->sent('/children'));
        $lookups = $this->sent('/shares/');

        // It is not asked again for a minute, by either page: not 59 seconds later...
        $this->microsoft['folder'] = $ok;
        $this->travel(59)->seconds();
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->listing()->assertStatus(503);
        $this->assertSame($lookups, $this->sent('/shares/'), 'the folder is not asked');
        $this->assertSame(0, $this->sent('/children'));

        // ...and after the minute it is.
        $this->travel(2)->seconds();
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertSame(1, $this->sent('/children'));
    }

    public function test_a_workbook_that_stalls_as_the_second_source_is_held_back_for_a_minute_like_on_its_own_page(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->listing()->assertOk()->assertJsonPath('register.stale', false);
        $this->assertSame(1, $this->sent('download.aspx'));

        // Eleven minutes on, the listing is read again at once; the workbook has another version, and its download stalls until the time is up.
        $this->travel(11)->minutes();
        $this->version = 2;
        $this->microsoft['download'] = self::timeout();
        $this->listing()->assertOk()->assertJsonPath('register.stale', true)->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->assertSame(2, $this->sent('download.aspx'));

        // The next click does not wait for it again, and the page of the workbook does not either: it is one minute for both pages.
        $this->listing(['search' => 'budi'])->assertOk()->assertJsonPath('register.stale', true);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true);
        $this->assertSame(2, $this->sent('download.aspx'));

        // After the minute it is asked again, and has recovered.
        $this->travel(61)->seconds();
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $this->listing()->assertOk()->assertJsonPath('register.stale', false);
        $this->assertSame(3, $this->sent('download.aspx'));

        // With nothing kept there is nothing to show instead; the click after it does not wait again either.
        $words = 'Kolom Masterlist dan Catatan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.';
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = self::timeout();
        $this->listing()->assertOk()->assertJsonPath('register.unavailable', $words);
        $downloads = $this->sent('download.aspx');
        $this->listing(['search' => 'budi'])->assertOk()->assertJsonPath('register.unavailable', $words);
        $this->assertSame($downloads, $this->sent('download.aspx'));

        // A download that was arriving, but too slowly, is a slow download: the same, in its own words, also on the page of the workbook.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = self::transfer(500_000, 100_000, self::timeout());
        $this->assertStringStartsWith('Kolom Masterlist dan Catatan belum bisa ditampilkan. Workbook terlalu lama diambil', $this->listing()->assertOk()->json('register.unavailable'));
        $downloads = $this->sent('download.aspx');
        $this->listing(['search' => 'budi'])->assertOk();
        $this->rows()->assertStatus(504)->assertJsonPath('reason', 'too_slow');
        $this->assertSame($downloads, $this->sent('download.aspx'));
    }

    public function test_running_out_of_a_window_that_was_cut_to_less_than_half_holds_nothing_back_and_a_longer_one_does(): void
    {
        $this->actingAs($this->superAdmin());
        $okFolder = fn () => Http::response(['value' => self::files()]);

        // The workbook as the second source can have 22 seconds at the best (the 27 of a request less the 5 it keeps for being read): half of it is 11.
        foreach ([[10.8, false], [11.2, true]] as [$folderTook, $cut]) {
            Cache::flush();
            $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
            $this->microsoft['children'] = $this->after($folderTook, $okFolder);
            $this->microsoft['download'] = self::timeout();
            $this->listing()->assertOk();
            $this->microsoft['download'] = fn () => Http::response($this->bytes);
            // The window was 27 - 5 - $folderTook seconds. Cut short, the failure says nothing about the workbook, and its own page goes on; else it is held back.
            $cut ? $this->rows()->assertOk() : $this->rows()->assertStatus(503);
        }

        // The folder as the second source can have 20 seconds at the best: half of it is 10 (a window of that length is a window like its own page's).
        foreach ([[16.8, false], [17.2, true]] as [$workbookTook, $cut]) {
            Cache::flush();
            $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
            $this->microsoft['download'] = $this->after($workbookTook, fn () => Http::response($this->bytes));
            $this->microsoft['children'] = self::timeout();
            $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
            $this->microsoft['children'] = $okFolder;
            $cut ? $this->listing()->assertOk() : $this->listing()->assertStatus(503);
        }

        // The whole of the 20 seconds can be used up in pieces: the lookup takes 12, the listing runs out of the 8 that are left (its request is made
        // shorter than usual). The window itself was not cut, so this is the folder's own trouble.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->rows()->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $okLookup = $this->microsoft['folder'];
        $this->microsoft['folder'] = $this->after(12, $okLookup);
        $this->microsoft['children'] = self::timeout();
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->microsoft['folder'] = $okLookup;
        $this->microsoft['children'] = $okFolder;
        $this->listing()->assertStatus(503);

        // The same with a window of 12 seconds (the workbook took 15): that is more than half of the best the folder can have (10), however it is
        // used up: the lookup takes 4, and the listing runs out of the 8 that are left.
        $unavailable = 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.';
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = $this->after(15, fn () => Http::response($this->bytes));
        $this->microsoft['folder'] = $this->after(4, $okLookup);
        $this->microsoft['children'] = self::timeout();
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', $unavailable);
        $this->microsoft['folder'] = $okLookup;
        $this->microsoft['children'] = $okFolder;
        $this->listing()->assertStatus(503);

        // A failure that is no matter of time is Microsoft's, whatever the window was: a 503 holds a source back even when the window was cut to less
        // than half (the workbook took 17.5 seconds: 9.5 are left for the folder).
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = $this->after(17.5, fn () => Http::response($this->bytes));
        $this->microsoft['children'] = fn () => Http::response('', 503);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', $unavailable);
        $this->microsoft['children'] = $okFolder;
        $this->listing()->assertStatus(503);

        // ...and the same for the workbook (the folder took 12 seconds: 10 are left for it, of the 22 that it can have at the best).
        $okBook = $this->microsoft['book'];
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $this->microsoft['children'] = $this->after(12, $okFolder);
        $this->microsoft['book'] = fn () => Http::response('', 503);
        $this->listing()->assertOk()->assertJsonPath(
            'register.unavailable',
            'Kolom Masterlist dan Catatan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.'
        );
        $this->microsoft['book'] = $okBook;
        $this->rows()->assertStatus(503);
    }

    public function test_the_second_source_is_given_no_more_than_its_own_page_gives_it_even_when_more_is_left(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');

        // The folder as the second source, the workbook at hand: its lookup takes 12 seconds, and the listing gets what is left of the 20 that
        // the folder has on its own page (8), not of the 27 that the request has (that would be 10, the most a request may take).
        $folderLookup = $this->microsoft['folder'];
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->rows()->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $this->sentWith = [];
        $this->microsoft['folder'] = $this->after(12, $folderLookup);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertEqualsWithDelta(8.0, $this->timeoutOf('/children'), 0.01);

        // The workbook as the second source, the folder at hand, and a limit of 10 seconds for a workbook: its lookup takes 4, the download gets the
        // 6 that are left of the 10 (not of the 22 that the request has left for it).
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['folder'] = $folderLookup;
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null, 'sharepoint-docs.preview_time_limit' => 10]);
        $this->listing()->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $this->sentWith = [];
        $book = $this->microsoft['book'];
        $this->microsoft['book'] = $this->after(4, $book);
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->assertEqualsWithDelta(6.0, $this->timeoutOf('download.aspx'), 0.01);
    }

    public function test_the_workbook_may_take_what_is_left_after_its_five_seconds_for_being_read(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        // The folder is cold and quick; the workbook is the second source: its first answer takes 20.5 seconds of the 22 that it may have
        // (27 less the 5 it keeps for being read), and the rest is just enough.
        $ok = $this->microsoft['book'];
        $this->microsoft['book'] = $this->after(20.5, $ok);
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');

        // A second and a half longer is more than it may have: the page says it did not get to the workbook.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['book'] = $this->after(23, $ok);
        $this->assertStringStartsWith('Kolom Masterlist dan Catatan belum bisa ditampilkan. ', $this->listing()->assertOk()->json('register.unavailable'));
    }

    public function test_a_second_source_that_cannot_be_reached_is_not_asked_for_again_for_a_minute(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $this->microsoft['children'] = fn () => Http::response('', 503);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $signIns = $this->sent('login.microsoftonline.com');
        $folderCalls = $this->sent('/shares/');

        // The next click costs nothing at Microsoft's: the folder is not asked for. ("Muat ulang" does not ask either: a minute is a minute.)
        $this->rows(['status' => 'ok'])->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->assertSame($signIns, $this->sent('login.microsoftonline.com'));
        $this->assertSame($folderCalls, $this->sent('/shares/'));

        // After the minute it is, and has recovered.
        $this->travel(61)->seconds();
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
    }

    public function test_a_real_outage_found_by_the_second_source_holds_its_own_page_back_for_a_minute(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        // The folder cannot be reached while the page of the workbook looks (it is not a matter of the time that was left)...
        $this->microsoft['children'] = fn () => Http::response('', 503);
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        // ...so the page of the folder does not try at once either.
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);
        $this->listing()->assertStatus(503);
        $this->travel(61)->seconds();
        $this->listing()->assertOk();

        // The same for the workbook, while the page of the folder looks.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $ok = $this->microsoft['book'];
        $this->microsoft['book'] = fn () => Http::response('', 503);
        $this->listing()->assertOk()->assertJsonPath('register.unavailable', 'Kolom Masterlist dan Catatan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->microsoft['book'] = $ok;
        $this->rows()->assertStatus(503);
        $this->travel(61)->seconds();
        $this->rows()->assertOk();
    }

    public function test_a_time_that_ran_out_on_the_folders_own_page_holds_it_back_for_a_minute(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        config(['sharepoint-docs.time_limit' => 5]);

        // The listing is given 5 seconds, and the request for it runs out of them (it is made shorter than usual).
        $this->microsoft['children'] = self::timeout();
        $this->listing()->assertStatus(503);
        // That is the folder's own trouble: it is not asked again for a minute...
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);
        $this->listing()->assertStatus(503);
        // ...and then it is.
        $this->travel(61)->seconds();
        $this->listing()->assertOk();
    }

    public function test_a_definite_answer_for_the_second_source_is_asked_again_at_the_next_request(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $ok = $this->microsoft['folder'];

        // The application has lost its permission for the folder...
        $this->microsoft['folder'] = fn () => Http::response(['error' => ['code' => 'accessDenied', 'message' => 'x']], 403);
        $this->assertStringStartsWith('Kolom File scan belum bisa ditampilkan. Aplikasi tidak punya izin', $this->rows()->assertOk()->json('sheet.scan.unavailable'));
        // ...and gets it back: the next request finds the folder (a definite answer is shown at once and does not hold anything back).
        $this->microsoft['folder'] = $ok;
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.total', 10);
    }

    public function test_when_the_folder_could_only_be_had_from_what_was_kept_the_workbook_is_not_asked_for_on_top_of_it(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->listing()->assertOk();

        // Both are old; the folder cannot be reached: its kept listing is shown, and the workbook is not tried on top of that.
        $this->travel(11)->minutes();
        $this->microsoft['children'] = fn () => Http::response('', 503);
        $downloads = $this->sent('download.aspx');
        $this->listing()->assertOk()
            ->assertJsonPath('folder.stale', true)
            ->assertJsonPath('register.unavailable', 'Kolom Masterlist dan Catatan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->assertSame($downloads, $this->sent('download.aspx'));
    }

    public function test_a_workbook_that_is_at_hand_is_used_even_when_the_folder_could_only_be_had_from_what_was_kept(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->listing()->assertOk();

        // Six minutes on, the workbook is read again (for its own page; the folder is not asked for)...
        $this->travel(6)->minutes();
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->rows(['refresh' => 1])->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $downloads = $this->sent('download.aspx');

        // ...five minutes after that the listing is too old and cannot be had: its kept copy is shown, and the workbook, which is recent enough,
        // is used on top of it.
        $this->travel(5)->minutes();
        $this->microsoft['children'] = fn () => Http::response('', 503);
        $this->listing()->assertOk()
            ->assertJsonPath('folder.stale', true)
            ->assertJsonPath('register.stale', false)
            ->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->assertSame($downloads, $this->sent('download.aspx'));
    }

    public function test_muat_ulang_does_not_count_what_is_older_than_15_seconds_as_at_hand(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->rows()->assertOk();
        $this->travel(20)->seconds();

        // The workbook has another version, and takes nearly all the time: the folder is 20 seconds old, which "Muat ulang" does not accept,
        // so the little time that is left is asked about (it is too little).
        $this->version = 2;
        $ok = $this->microsoft['download'];
        $this->microsoft['download'] = $this->after(24.5, $ok);
        $this->rows(['refresh' => 1])->assertOk()
            ->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.');

        // The same for the workbook while the page of the folder looks.
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->version = 1;
        $this->microsoft['download'] = $ok;
        $this->listing()->assertOk();
        $this->travel(20)->seconds();
        $this->version = 2;
        $this->microsoft['children'] = $this->after(19.5, fn () => Http::response(['value' => self::files()]));
        $this->listing(['refresh' => 1])->assertOk()
            ->assertJsonPath('register.unavailable', 'Kolom Masterlist dan Catatan belum bisa ditampilkan. Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.');
    }

    public function test_when_the_first_source_could_only_be_had_from_what_was_kept_the_second_is_not_asked_for_on_top_of_it(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.stale', false);

        // Both are old now; Microsoft cannot be reached for the workbook: its kept copy is shown. The folder is not tried on top of that.
        $this->travel(11)->minutes();
        $this->microsoft['book'] = fn () => Http::response('', 503);
        $folderCalls = $this->sent('/children');
        $this->rows()->assertOk()
            ->assertJsonPath('workbook.stale', true)
            ->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->assertSame($folderCalls, $this->sent('/children'));
    }

    public function test_a_second_source_that_is_at_hand_is_used_even_when_the_first_could_only_be_had_from_what_was_kept(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->rows()->assertOk();

        // Five minutes on the folder is read again (for its own page; the workbook is not asked for)...
        $this->travel(5)->minutes();
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->listing(['refresh' => 1])->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $folderCalls = $this->sent('/children');
        $this->assertSame(2, $folderCalls);

        // ...six minutes after that the workbook is too old, and cannot be had: its kept copy is shown; the folder is still recent enough to be used.
        $this->travel(6)->minutes();
        $this->microsoft['book'] = fn () => Http::response('', 503);
        $this->rows()->assertOk()
            ->assertJsonPath('workbook.stale', true)
            ->assertJsonPath('sheet.scan.stale', false)
            ->assertJsonPath('sheet.scan.counts.total', 10);
        $this->assertSame($folderCalls, $this->sent('/children'));
    }

    public function test_muat_ulang_reaches_the_other_source_on_both_pages(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.ok', 4);
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');

        // A little later: the folder has got the scan of row 4 (ICTCOF3), and the workbook has a note for row 2 and another version.
        $this->travel(20)->seconds();
        $this->microsoft['children'] = fn () => Http::response(['value' => array_merge(self::files(), [self::file('ICTCOF3 - Citra Dewi.pdf', '2026-01-08T00:00:00Z')])]);
        $this->version = 2;
        $this->bytes = self::xlsx(array_replace(self::book(), ['COF' => array_replace(self::book()['COF'], [1 => [1, 'ICT/COF/1', 'Sudah diterima', self::link('ICTCOF1 - Budi Santoso.pdf')]])]));

        // Without asking again, what is kept is shown.
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.counts.ok', 4);
        $this->assertSame('Menyusul', $this->listing()->json('data.1.note'));

        // "Muat ulang" on the page of the workbook asks the folder again (and the workbook, which has another version now)...
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('sheet.scan.counts.ok', 5)->assertJsonPath('sheet.scan.counts.missing', 2);
        $this->assertSame(2, $this->sent('download.aspx'));
        $this->assertSame(2, $this->sent('/children'));

        // ...and on the page of the folder it asks the workbook again: here the folder has a new scan, and the workbook a new note.
        $this->travel(20)->seconds();
        $this->bytes = self::xlsx(array_replace(self::book(), ['COF' => array_replace(self::book()['COF'], [1 => [1, 'ICT/COF/1', 'Nanti lagi', self::link('ICTCOF1 - Budi Santoso.pdf')]])]));
        $this->version = 3;
        $before = collect($this->listing()->json('data'))->keyBy('name');
        $this->assertSame('Sudah diterima', $before['ICTCOF1 - Budi Santoso.pdf']['note'], 'what is kept is shown until it is asked for again');
        $after = collect($this->listing(['refresh' => 1])->assertOk()->json('data'))->keyBy('name');
        $this->assertSame('Nanti lagi', $after['ICTCOF1 - Budi Santoso.pdf']['note']);
        $this->assertSame('linked', $after['ICTCOF1 - Budi Santoso.pdf']['masterlist']['status']);
        $this->assertSame(3, $this->sent('download.aspx'));
    }

    // ================================================================ the page of the folder

    public function test_the_folder_names_for_every_file_the_row_that_names_it_and_the_note_of_that_row(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $response = $this->listing()->assertOk();

        $data = $response->json('data');
        $this->assertSame(
            ['Lama', 'ICTCOF1 - Budi Santoso.pdf', 'ICTCOF2 - Andi  Wijaya.pdf', 'ICTCOF4 - Eka Lestari.pdf', 'ICTCOF7 - Gita  Lestari.pdf', 'ICTCOF8 - Hadi.pdf', 'ICTCOF9 - Tanpa Baris.pdf', 'ICTCOF10 - Joko.pdf'],
            array_column($data, 'name')
        );

        // A folder is told nothing.
        $this->assertArrayNotHasKey('masterlist', $data[0]);
        $this->assertArrayNotHasKey('note', $data[0]);
        // The file a row names: the row, the way to it (the row of Excel, and no other), the note.
        $this->assertSame(['status' => 'linked', 'line' => 2, 'more' => 0, 'url' => '/documents/ict-masterlist?sheet=COF&line=2'], $data[1]['masterlist']);
        $this->assertSame('Menyusul', $data[1]['note']);
        // One space in the row's link where the file has two: a row has its number, not its name.
        $this->assertSame(['status' => 'differs', 'line' => 3, 'more' => 0, 'url' => '/documents/ict-masterlist?sheet=COF&line=3'], $data[2]['masterlist']);
        $this->assertNull($data[2]['note']);
        $this->assertSame(['status' => 'linked', 'line' => 5, 'more' => 0, 'url' => '/documents/ict-masterlist?sheet=COF&line=5'], $data[3]['masterlist']);
        $this->assertSame('Rangkap', $data[3]['note']);
        // Two spaces in the link as in the name: the row names the file.
        $this->assertSame(['status' => 'linked', 'line' => 8, 'more' => 0, 'url' => '/documents/ict-masterlist?sheet=COF&line=8'], $data[4]['masterlist']);
        // A row without a link has the file's number: the same pair as the sheet's "Nama beda".
        $this->assertSame(['status' => 'differs', 'line' => 9, 'more' => 0, 'url' => '/documents/ict-masterlist?sheet=COF&line=9'], $data[5]['masterlist']);
        // No row at all: nothing to go to.
        $this->assertSame(['status' => 'unlinked', 'line' => null, 'more' => 0, 'url' => null], $data[6]['masterlist']);
        $this->assertNull($data[6]['note']);
        $this->assertSame(['status' => 'linked', 'line' => 10, 'more' => 0, 'url' => '/documents/ict-masterlist?sheet=COF&line=10'], $data[7]['masterlist']);

        $response->assertJsonPath('register', ['title' => 'ICT Masterlist', 'sheet' => 'COF', 'fetched_at' => '07-10-2026 10:15', 'stale' => false]);

        // The way to the row is a link that the page can follow, and what it asks for is that row.
        $this->get($data[1]['masterlist']['url'])->assertOk();
        $this->rows(['line' => '2'])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.line', 2);
    }

    public function test_a_file_that_many_rows_name_tells_of_them_and_a_note_is_the_first_that_is_there(): void
    {
        $this->actingAs($this->superAdmin());
        $rows = self::book();
        // Rows 12 and 13 name the file of row 2 once more (the note of the first of them is empty), and row 14 has its number.
        $rows['COF'][] = [11, 'ICT/COF/1', '', self::link('ICTCOF1 - Budi Santoso.pdf')];
        $rows['COF'][] = [12, 'ICT/COF/1', 'Kedua', self::link('ictcof1 - budi santoso.PDF')];
        $rows['COF'][] = [13, 'ICT/COF/2', 'Ketiga', null];
        $this->bytes = self::xlsx($rows);

        $data = $this->listing()->assertOk()->json('data');

        $this->assertSame(['status' => 'linked', 'line' => 2, 'more' => 2, 'url' => '/documents/ict-masterlist?sheet=COF&line=2%2C12%2C13'], $data[1]['masterlist']);
        $this->assertSame('Menyusul', $data[1]['note'], 'the first row that has a note');
        // ICTCOF2: its own row (line 3) and the row without a link (line 14) have its number.
        $this->assertSame(['status' => 'differs', 'line' => 3, 'more' => 1, 'url' => '/documents/ict-masterlist?sheet=COF&line=3%2C14'], $data[2]['masterlist']);
        $this->assertSame('Ketiga', $data[2]['note']);
        $this->rows(['line' => '2,12,13'])->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_a_file_that_more_than_twenty_rows_name_tells_of_twenty_and_counts_the_others(): void
    {
        $this->actingAs($this->superAdmin());
        $rows = self::book();
        for ($i = 0; $i < 24; $i++) {
            $rows['COF'][] = [20 + $i, 'ICT/COF/1', null, self::link('ICTCOF1 - Budi Santoso.pdf')];
        }
        $this->bytes = self::xlsx($rows);

        $masterlist = $this->listing()->assertOk()->json('data.1.masterlist');

        // 25 rows name it: the first 20 are in the way to them, the 24 other than the first are counted.
        $this->assertSame(24, $masterlist['more']);
        $this->assertSame(2, $masterlist['line']);
        parse_str((string) parse_url($masterlist['url'], PHP_URL_QUERY), $query);
        $this->assertSame(array_merge([2], range(12, 30)), array_map('intval', explode(',', $query['line'])));
        $this->assertCount(20, explode(',', $query['line']));
        $this->rows(['line' => $query['line']])->assertOk()->assertJsonPath('meta.total', 20);
    }

    public function test_a_workbook_that_is_at_hand_is_used_when_the_folder_leaves_too_little_time_to_read_one(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        // The workbook was read a moment ago (its page was open, without the folder).
        $scans = config('sharepoint-docs.workbooks.ict-masterlist.scans');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);
        $this->rows()->assertOk();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => $scans]);
        $this->assertSame(1, $this->sent('download.aspx'));

        // The folder takes 19.5 of the 27 seconds: a workbook that had to be fetched would have 2.5 of them after the 5 it needs for being read, and
        // is not asked for; this one is at hand, and costs none.
        $this->microsoft['children'] = $this->after(19.5, fn () => Http::response(['value' => self::files()]));
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->assertSame(1, $this->sent('download.aspx'), 'the workbook was not downloaded again');
    }

    public function test_only_the_files_with_a_document_number_are_listed_when_it_is_asked_for(): void
    {
        $this->actingAs($this->superAdmin());

        $one = $this->listing(['code' => 'ICTCOF1'])->assertOk();
        $this->assertSame(['ICTCOF1 - Budi Santoso.pdf'], $this->namesOf($one), 'ICTCOF1, not ICTCOF10 as well');
        $one->assertJsonPath('folder.code', 'ICTCOF1')->assertJsonPath('meta.total', 1);
        $this->assertSame(['ICTCOF10 - Joko.pdf'], $this->namesOf($this->listing(['code' => 'ICTCOF10'])));
        // However the number is written (the way the register writes it, with the case and the signs of a name).
        $this->assertSame(['ICTCOF2 - Andi  Wijaya.pdf'], $this->namesOf($this->listing(['code' => 'ict/cof/2'])));
        $this->assertSame(['ICTCOF2 - Andi  Wijaya.pdf'], $this->namesOf($this->listing(['code' => ' ICT-COF-2 '])));
        // No file with it: none, and the folder is not shown instead.
        $none = $this->listing(['code' => 'ICTCOF77'])->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('folder.code', 'ICTCOF77');
        $this->assertSame([], $this->namesOf($none));
        // Searched for as well: both count.
        $this->assertSame([], $this->namesOf($this->listing(['code' => 'ICTCOF1', 'search' => 'tidak ada'])));
        $this->assertSame(['ICTCOF1 - Budi Santoso.pdf'], $this->namesOf($this->listing(['code' => 'ICTCOF1', 'search' => 'budi'])));
        // The whole folder says how many files it has, whatever is asked for.
        $one->assertJsonPath('folder.files', 7)->assertJsonPath('folder.folders', 1);

        // A folder is no file with a number, whatever its name looks like.
        $this->assertSame([], $this->namesOf($this->listing(['code' => 'Lama'])));
        // The number of a file is what comes before the separator that the folder's setting names: with another one, no file has the number.
        config(['sharepoint-docs.folders.cof-scan.name_split.separator' => ' ~ ']);
        $this->assertSame([], $this->namesOf($this->listing(['code' => 'ICTCOF1'])));
        config(['sharepoint-docs.folders.cof-scan.name_split.separator' => ' - ']);
        $this->assertSame(['ICTCOF1 - Budi Santoso.pdf'], $this->namesOf($this->listing(['code' => 'ICTCOF1'])));

        // Nothing that is a number is no number: the whole folder.
        foreach (['', ' ', ' / ', '---'] as $text) {
            $this->listing(['code' => $text])->assertOk()->assertJsonPath('meta.total', 8)->assertJsonMissingPath('folder.code');
        }
        $this->listing(['code' => ['ICTCOF1']])->assertOk()->assertJsonPath('meta.total', 8);
        // It is no more than 400 characters, the longest name a file can have.
        $long = $this->listing(['code' => 'ICTCOF1' . str_repeat('x', 500)])->assertOk();
        $this->assertSame(400, mb_strlen($long->json('folder.code')));
        $this->assertSame(ScanRegister::CODE_MOST, mb_strlen($long->json('folder.code')));
    }

    public function test_a_file_whose_number_is_its_whole_name_is_found_by_it_and_a_folder_without_a_separator_is_read_as_the_register_reads_it(): void
    {
        $this->actingAs($this->superAdmin());
        // No " - " in the name: the number of the file is all of it, and it is longer than a number is.
        $name = 'ICTCOF123-Budi Santoso Putra Pratama Wijaya Kusuma.pdf';
        $this->microsoft['children'] = fn () => Http::response(['value' => array_merge(self::files(), [self::file($name, '2026-01-09T00:00:00Z')])]);
        $rows = self::book();
        $rows['COF'][] = [11, 'ICT/COF/123', null, self::link($name)];
        $this->bytes = self::xlsx($rows);

        // The row names it, and goes to it by its number (all of the name): the page of the folder finds the file by that.
        $row = $this->rows(['line' => '12'])->assertOk();
        $this->assertSame('ok', $row->json('data.0.scan.status'));
        $code = $row->json('data.0.scan.code');
        $this->assertSame('ICTCOF123-Budi Santoso Putra Pratama Wijaya Kusuma', $code);
        $this->assertGreaterThan(40, mb_strlen($code));
        $this->assertSame([$name], $this->namesOf($this->listing(['code' => $code])->assertOk()->assertJsonPath('meta.total', 1)));

        // A folder that has no separator in its settings: the register reads the numbers by the separator the names have, and so does the page.
        config(['sharepoint-docs.folders.cof-scan.name_split' => null]);
        $this->assertSame(['ICTCOF1 - Budi Santoso.pdf'], $this->namesOf($this->listing(['code' => 'ICTCOF1'])));
        $this->assertSame([$name], $this->namesOf($this->listing(['code' => $code])));
    }

    public function test_a_document_number_can_be_asked_for_where_no_sheet_lists_the_files(): void
    {
        $this->actingAs($this->superAdmin());
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);

        $response = $this->listing(['code' => 'ICTCOF4'])->assertOk()->assertJsonMissingPath('register')->assertJsonPath('folder.code', 'ICTCOF4');

        $this->assertSame(['ICTCOF4 - Eka Lestari.pdf'], $this->namesOf($response));
    }

    public function test_the_files_are_sorted_by_what_is_left_to_do(): void
    {
        $this->actingAs($this->superAdmin());

        $by = fn (string $dir) => $this->namesOf($this->listing(['sort' => 'masterlist', 'dir' => $dir])->assertOk());

        // Folders first as always; then the file without a row, those with another name, and those a row names (each in the order of their names).
        $this->assertSame(
            ['Lama', 'ICTCOF9 - Tanpa Baris.pdf', 'ICTCOF2 - Andi  Wijaya.pdf', 'ICTCOF8 - Hadi.pdf', 'ICTCOF1 - Budi Santoso.pdf', 'ICTCOF4 - Eka Lestari.pdf', 'ICTCOF7 - Gita  Lestari.pdf', 'ICTCOF10 - Joko.pdf'],
            $by('asc')
        );
        $this->assertSame(
            ['Lama', 'ICTCOF1 - Budi Santoso.pdf', 'ICTCOF4 - Eka Lestari.pdf', 'ICTCOF7 - Gita  Lestari.pdf', 'ICTCOF10 - Joko.pdf', 'ICTCOF2 - Andi  Wijaya.pdf', 'ICTCOF8 - Hadi.pdf', 'ICTCOF9 - Tanpa Baris.pdf'],
            $by('desc')
        );
    }

    public function test_a_folder_below_is_told_by_the_names_of_the_links_wherever_they_point(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->listing(['path' => 'Lama'])->assertOk();

        // The fifth row's link names "La Tantri.pdf" in a folder that has another name than the one the file is in: it is the row's own file.
        $this->assertSame('linked', $response->json('data.0.masterlist.status'));
        $this->assertSame(6, $response->json('data.0.masterlist.line'));
        $this->assertSame('/documents/ict-masterlist?sheet=COF&line=6', $response->json('data.0.masterlist.url'));
    }

    public function test_the_folder_is_shown_as_it_was_when_the_workbook_cannot_be_had(): void
    {
        $this->actingAs($this->superAdmin());

        $this->microsoft['download'] = fn () => Http::response('bukan workbook', 200);
        $response = $this->listing()->assertOk();

        $response->assertJsonPath('meta.total', 8)
            ->assertJsonPath('register.title', 'ICT Masterlist')
            ->assertJsonPath('register.unavailable', 'Kolom Masterlist dan Catatan belum bisa ditampilkan. SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.')
            ->assertJsonMissingPath('register.sheet')
            ->assertJsonMissingPath('data.1.masterlist')
            ->assertJsonMissingPath('data.1.note');

        // The workbook has no sheet of that title, or the sheet no column of that title.
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        Cache::flush();
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => ['Lain' => ['folder' => 'cof-scan', 'link' => 'Tautan']]]);
        $this->listing()->assertOk()->assertJsonPath('register.unavailable', 'Sheet Lain tidak ada di workbook ICT Masterlist, jadi kolom Masterlist dan Catatan tidak bisa ditampilkan.');
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['folder' => 'cof-scan', 'link' => 'Alamat']]]);
        $this->listing()->assertOk()->assertJsonPath('register.unavailable', 'Kolom "Alamat" tidak ditemukan di sheet COF, jadi kolom Masterlist dan Catatan tidak bisa ditampilkan.');
    }

    public function test_a_sheet_that_is_cut_off_cannot_tell_which_files_have_no_row(): void
    {
        $this->actingAs($this->superAdmin());
        // The workbook says its COF sheet is longer than what is read: the files of the rows that were left out would all read "Tidak ada baris".
        $this->app->bind(SharePointWorkbook::class, fn ($app) => new class($app->make(GraphClient::class), $app->make(WorkbookReader::class)) extends SharePointWorkbook {
            public function tables(string $key, bool $refresh = false): array
            {
                $book = parent::tables($key, $refresh);
                $book['sheets'][1]['truncated'] = true;

                return $book;
            }
        });

        $this->listing()->assertOk()
            ->assertJsonPath('meta.total', 8)
            ->assertJsonPath('register.unavailable', 'Sheet COF terlalu panjang untuk dibaca seluruhnya, jadi kolom Masterlist dan Catatan tidak bisa dihitung.')
            ->assertJsonMissingPath('data.1.masterlist');
    }

    public function test_a_folder_that_no_sheet_lists_is_shown_as_it_always_was(): void
    {
        $this->actingAs($this->superAdmin());
        config(['sharepoint-docs.workbooks.ict-masterlist.scans' => null]);

        $response = $this->listing()->assertOk();

        $response->assertJsonMissingPath('register')->assertJsonMissingPath('data.1.masterlist')->assertJsonMissingPath('data.1.note');
        $this->assertSame(1, $this->sent('/shares/'), 'only the folder was looked up: the workbook is not asked for');
        $this->assertSame(0, $this->sent('/content'));
        // The page offers no columns for it.
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('data-key="masterlist"', false)->assertDontSee('>Catatan</th>', false)
            ->assertDontSee('docs-register" id="docsTable"', false);
    }

    public function test_a_folder_whose_workbook_is_not_set_up_is_shown_as_it_always_was(): void
    {
        $this->actingAs($this->superAdmin());
        config(['sharepoint-docs.workbooks.ict-masterlist.url' => '']);

        $this->listing()->assertOk()->assertJsonMissingPath('register')->assertJsonMissingPath('data.1.masterlist')->assertJsonMissingPath('data.1.note');
        $this->assertSame(0, $this->sent('/content'));
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('data-key="masterlist"', false);

        // The page of the workbook itself says why it has nothing to show.
        $this->rows()->assertStatus(503)->assertJsonPath('message', 'Alamat workbook atau akun aplikasi Microsoft belum diisi di pengaturan (.env).')->assertJsonPath('reason', 'not_configured');

        // The same with the account of the application not filled in: nothing to read either.
        config(['sharepoint-docs.workbooks.ict-masterlist.url' => self::BOOK_ADDRESS, 'services.azure.client_secret' => '']);
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('data-key="masterlist"', false);
    }

    public function test_the_folder_gets_the_workbook_only_for_who_may_open_the_workbook(): void
    {
        $viewer = $this->makeUser('viewer.radio@test.local', 'Viewer Radio');
        $this->grant('Viewer Radio', CofScanMenuSeeder::ROUTE);
        $this->actingAs($viewer->fresh());

        // May read the folder, not the workbook: the files only, and the workbook is not asked for.
        $this->listing()->assertOk()->assertJsonMissingPath('register')->assertJsonMissingPath('data.1.masterlist');
        $this->assertSame(0, $this->sent('/content'));
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('data-key="masterlist"', false);

        $this->grant('Viewer Radio', IctMasterlistMenuSeeder::ROUTE);
        $this->actingAs($viewer->fresh());
        $this->listing()->assertOk()->assertJsonPath('data.1.masterlist.status', 'linked');
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertSee('data-key="masterlist"', false);
    }

    public function test_the_page_of_the_folder_and_the_page_of_the_workbook_say_what_they_add(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('documents.cof-scan.index'))->assertOk()
            ->assertSee('data-key="masterlist"', false)
            ->assertSee('>Catatan</th>', false)
            ->assertSee('Kolom Masterlist dan Catatan dari sheet COF di ICT Masterlist', false)
            ->assertSee('id="docsCodeBar"', false)
            // The name is given room beside the two columns (see the style of the page): only where they are.
            ->assertSee('rm-table docs-register" id="docsTable"', false);
        $this->get(route('documents.ict-masterlist.index'))->assertOk()
            ->assertSee('id="wbCardsWrap"', false)
            ->assertSee('id="wbLineBar"', false)
            ->assertSee('id="wbSearchLabel"', false)
            ->assertSee('huruf besar atau kecil tidak dibedakan', false)
            ->assertSee('pencarian tidak mencari di kolom ini', false);
    }

    // ================================================================ when the other source goes wrong, and what is written down

    public function test_what_the_other_source_answers_is_told_in_the_words_of_the_page_that_has_it(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $ok = $this->microsoft;
        $gone = fn () => Http::response(['error' => ['code' => 'itemNotFound', 'message' => 'secret detail']], 404);

        // The folder is not there any more: the page of the workbook says it in the words of a folder...
        $this->microsoft['folder'] = $gone;
        $this->rows()->assertOk()->assertJsonPath(
            'sheet.scan.unavailable',
            'Kolom File scan belum bisa ditampilkan. Folder tidak ditemukan di SharePoint. Mungkin sudah dipindahkan atau dihapus, atau alamat di pengaturan keliru.'
        );

        // ...and the page of the folder, when the workbook is not, in the words of a workbook.
        $this->microsoft = $ok;
        $this->microsoft['book'] = $gone;
        Cache::flush();
        $response = $this->listing()->assertOk();
        $response->assertJsonPath(
            'register.unavailable',
            'Kolom Masterlist dan Catatan belum bisa ditampilkan. Workbook tidak ditemukan di SharePoint. Mungkin sudah dipindahkan, diganti namanya atau dihapus, atau alamat di pengaturan keliru.'
        );
        $this->assertStringNotContainsString('secret detail', (string) $response->getContent());

        // Refused: no permission.
        $this->microsoft['book'] = fn () => Http::response(['error' => ['code' => 'accessDenied', 'message' => 'secret detail']], 403);
        Cache::flush();
        $this->listing()->assertOk()->assertJsonPath(
            'register.unavailable',
            'Kolom Masterlist dan Catatan belum bisa ditampilkan. Aplikasi tidak punya izin membaca workbook ini di SharePoint (perlu izin aplikasi Sites.Read.All dengan admin consent).'
        );
    }

    public function test_a_second_source_that_goes_wrong_is_written_down_with_why_and_a_source_that_is_not_asked_is_not(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $ok = $this->microsoft;
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = [$event->level, $event->message, $event->context['reason'] ?? null];
        });

        // The folder cannot be read for the page of the workbook...
        $this->microsoft['folder'] = fn () => Http::response(['error' => ['code' => 'accessDenied', 'message' => 'x']], 403);
        $this->rows()->assertOk();
        $this->assertContains(['warning', 'The files of folder "cof-scan" could not be read to check a sheet against them.', 'forbidden'], $logged);

        // ...and the workbook for the page of the folder.
        $logged = [];
        $this->microsoft = $ok;
        $this->microsoft['book'] = fn () => Http::response(['error' => ['code' => 'itemNotFound', 'message' => 'x']], 404);
        Cache::flush();
        $this->listing()->assertOk();
        $this->assertContains(['warning', 'The workbook "ict-masterlist" could not be read to check the files of a folder against it.', 'not_found'], $logged);

        // A source that was not asked at all (no time left) is nothing to write down.
        $logged = [];
        $this->microsoft = $ok;
        Cache::flush();
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        $this->microsoft['download'] = $this->after(24.5, fn () => Http::response($this->bytes));
        $this->rows()->assertOk()->assertJsonPath('sheet.scan.unavailable', 'Kolom File scan belum bisa ditampilkan. Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.');
        $this->assertSame([], array_filter($logged, fn (array $line) => str_contains($line[1], 'to check')));
    }

    // ================================================================ the setting that ships with the application

    public function test_the_setting_that_ships_names_a_folder_that_is_set_up_and_the_titles_the_register_has(): void
    {
        $config = require base_path('modules/SharePointDocs/Config/sharepoint-docs.php');

        $scans = $config['workbooks']['ict-masterlist']['scans'];
        $this->assertSame(['COF' => ['folder' => 'cof-scan', 'link' => 'Column4', 'code' => 'COF No', 'note' => 'Note']], $scans);
        $this->assertArrayHasKey('cof-scan', $config['folders'], 'the folder that the sheet is tied to is one of the folders');
        $this->assertSame(' - ', $config['folders']['cof-scan']['name_split']['separator']);
    }

    /** The share id that the workbook is looked up by (the part of its "/shares/…/driveItem" that names it). */
    private function workbookShare(): string
    {
        return 'u!' . rtrim(strtr(base64_encode(self::BOOK_ADDRESS), '+/', '-_'), '=');
    }
}
