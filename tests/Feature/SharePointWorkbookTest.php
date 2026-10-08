<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use Database\Seeders\CofScanMenuSeeder;
use Database\Seeders\IctMasterlistMenuSeeder;
use Database\Seeders\RadioMenuSeeder;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as GraphRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\WorkbookReader;
use Modules\SharePointDocs\Services\XlsxReader;
use Tests\Concerns\MakesTransfers;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\MakesWorkbooks;
use Tests\TestCase;

/**
 * The "ICT Masterlist" page: an Excel workbook in SharePoint shown as tables. Microsoft is played
 * by Http::fake() and the workbook is made here; every name, number and address is made up.
 */
class SharePointWorkbookTest extends TestCase
{
    use MakesTransfers;
    use MakesUsers;
    use MakesWorkbooks;
    use RefreshDatabase;

    protected bool $seed = true;

    /** The link as it stands in the browser while the workbook is open in Excel for the web. */
    private const ADDRESS = 'https://contoso.sharepoint.com/:x:/r/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B11111111-2222-3333-4444-555555555555%7D&file=Daftar%20Induk.xlsx&action=default';

    private const WEB_URL = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D&file=Daftar%20Induk.xlsx';

    private const DOWNLOAD = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/download.aspx';

    private const MIGRATION = 'migrations/2026_10_07_110000_add_ict_masterlist_menu.php';

    /** @var array<string, callable> what the pretend Microsoft answers: 'token', 'item', 'content', 'download' */
    private array $microsoft = [];

    /** The workbook the pretend SharePoint holds, and its version (changes when the file is saved). */
    private string $bytes = '';

    private string $version = '"{BOOK},1"';

    /** @var array<int, array{0: string, 1: array}> every request's address and the options it was sent with */
    private array $sentWith = [];

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config([
            'logging.default' => 'null',
            'services.azure.tenant' => 'tenant-1',
            'services.azure.client_id' => 'client-1',
            'services.azure.client_secret' => 'secret-1',
            'sharepoint-docs.workbooks.ict-masterlist.url' => self::ADDRESS,
            'sharepoint-docs.workbooks.ict-masterlist.sheets' => null,
            'sharepoint-docs.workbooks.ict-masterlist.skip_unused_rows' => false,
            'sharepoint-docs.cache_minutes' => 10,
            'app.timezone' => 'Asia/Makassar',
        ]);
        $this->bytes = self::xlsx(self::book());

        $this->microsoft = [
            'token' => fn () => Http::response(['token_type' => 'Bearer', 'expires_in' => 3599, 'access_token' => 'token-1']),
            'item' => fn () => Http::response($this->item()),
            'content' => fn () => Http::response('', 302, ['Location' => self::DOWNLOAD . '?UniqueId=1&tempauth=one-time']),
            'download' => fn () => Http::response($this->bytes),
        ];

        Http::fake(function (GraphRequest $request, array $options) {
            $url = $request->url();
            $this->sentWith[] = [$url, $options];

            return match (true) {
                str_starts_with($url, 'https://login.microsoftonline.com/') => $this->microsoft['token']($request, $options),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/shares/') => $this->microsoft['item']($request, $options),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/drives/') => $this->microsoft['content']($request, $options),
                str_starts_with($url, self::DOWNLOAD) => $this->microsoft['download']($request, $options),
                default => Http::response('not Microsoft', 500),
            };
        });
    }

    /** What Graph says about the file behind the address. */
    private function item(array $other = []): array
    {
        return $other + [
            'id' => 'BOOK1',
            'name' => 'Daftar Induk.xlsx',
            'size' => strlen($this->bytes),
            'eTag' => $this->version,
            'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'lastModifiedDateTime' => '2026-10-06T07:01:28Z',
            'webUrl' => self::WEB_URL,
            'parentReference' => ['driveId' => 'b!drive-1'],
        ];
    }

    /**
     * Two registers as they are kept in such a workbook, a sheet that is hidden in Excel, and an empty one.
     * Dates are Excel's day numbers: 45000 is 15-03-2023.
     */
    private static function book(): array
    {
        return [
            'SRF' => [
                ['SRF No', 'Date', 'Requested By', 'Qty', 'Value', 'Serial Number', 'Check'],
                ['ICT/SRF/1', ['value' => 45000, 'format' => 'd-mmm-yy'], 'Budi Santoso', 2, ['value' => 1500000, 'format' => '#,##0'], '000123', ['formula' => '=D2*2']],
                ['ICT/SRF/2', ['value' => 45001.5, 'format' => 'dd/mm/yyyy hh:mm'], 'Andi Wijaya', 10, ['value' => 250000.5, 'format' => '#,##0.00'], 'SN-9', null],
                ['ICT/SRF/10', null, 'Citra Dewi', null, null, null, null],
                [null, null, null, null, null, null, null],
                ['ICT/SRF/3', ['value' => 44999, 'format' => 'd-mmm-yy'], "Dewi\nLestari", 1, null, 'sn-10', true],
            ],
            'Kartu HALO' => [
                ['Daftar kartu HALO'],
                ['BAK No', 'User', 'No. MSISDN'],
                ['BAK/1', 'Budi Santoso', '0800000111'],
                ['BAK/2', 'Eka <b>Putra</b>', '0800000222'],
            ],
            '~Loan IT' => [
                ['LOA No', 'User', 'Device'],
                ['LOA/1', 'Budi Santoso', 'Laptop'],
            ],
            'Kosong' => [],
        ];
    }

    private function rows(array $query = []): TestResponse
    {
        return $this->getJson(route('documents.ict-masterlist.rows', $query));
    }

    /** How many requests went to an address containing $part. */
    private function sent(string $part): int
    {
        return Http::recorded(fn (GraphRequest $request) => str_contains($request->url(), $part))->count();
    }

    private static function graphError(int $status, string $code): callable
    {
        return fn () => Http::response(['error' => ['code' => $code, 'message' => "Microsoft says {$code} (token-1, secret-1)"]], $status);
    }

    public function test_the_page_shows_the_sheets_of_the_workbook_as_tables(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:15:00', 'UTC'));

        $this->get(route('documents.ict-masterlist.index'))->assertOk()
            ->assertSee('ICT Masterlist')
            ->assertSee('Buka di Excel')
            ->assertDontSee('sourcedoc', false); // the configured address is used to find the workbook, never shown
        Http::assertNothingSent();

        $this->rows()->assertOk()
            // One tab per sheet that is visible in Excel; "Loan IT" is hidden there.
            ->assertJsonPath('sheets', [
                ['index' => 0, 'name' => 'SRF', 'rows' => 4, 'matches' => null],
                ['index' => 1, 'name' => 'Kartu HALO', 'rows' => 2, 'matches' => null],
                ['index' => 2, 'name' => 'Kosong', 'rows' => 0, 'matches' => null],
            ])
            ->assertJsonPath('sheet', [
                'index' => 0,
                'name' => 'SRF',
                'columns' => ['SRF No', 'Date', 'Requested By', 'Qty', 'Value', 'Serial Number', 'Check'],
                'sort' => 'row',
                'dir' => 'desc',
                'truncated' => false,
            ])
            // The last row of the sheet first; every cell as the text Excel shows, numbers written the Indonesian way.
            ->assertJsonPath('data', [
                ['line' => 6, 'cells' => ['ICT/SRF/3', '14-03-2023', 'Dewi Lestari', '1', '', 'sn-10', 'TRUE']],
                ['line' => 4, 'cells' => ['ICT/SRF/10', '', 'Citra Dewi', '', '', '', '']],
                ['line' => 3, 'cells' => ['ICT/SRF/2', '16-03-2023 12:00', 'Andi Wijaya', '10', '250.000,50', 'SN-9', '']],
                ['line' => 2, 'cells' => ['ICT/SRF/1', '15-03-2023', 'Budi Santoso', '2', '1.500.000', '000123', '4']],
            ])
            ->assertJsonPath('meta', ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'per_page' => 25, 'to' => 4, 'total' => 4])
            ->assertJsonPath('workbook', [
                'name' => 'Daftar Induk.xlsx',
                'modified' => '06-10-2026 15:01',
                'fetched_at' => '07-10-2026 10:15',
                'web_url' => self::WEB_URL,
                'stale' => false,
            ]);

        // The application signs in as itself, finds the file from the pasted address, and reads its bytes
        // the way a file of a folder is read: Graph points to a one-time address, asked without the token.
        $share = 'u!' . rtrim(strtr(base64_encode(self::ADDRESS), '+/', '-_'), '=');
        Http::assertSent(fn (GraphRequest $request) => $request->url() === "https://graph.microsoft.com/v1.0/shares/{$share}/driveItem"
            && $request->hasHeader('Authorization', 'Bearer token-1'));
        Http::assertSent(fn (GraphRequest $request) => $request->url() === 'https://graph.microsoft.com/v1.0/drives/b!drive-1/items/BOOK1/content'
            && $request->hasHeader('Authorization', 'Bearer token-1'));
        Http::assertSent(fn (GraphRequest $request) => str_starts_with($request->url(), self::DOWNLOAD) && ! $request->hasHeader('Authorization'));
        Http::assertSentCount(4);

        // A sheet with a heading above its table: the titles are the first row that is filled like one.
        $this->rows(['sheet' => 1])->assertOk()
            ->assertJsonPath('sheet.columns', ['BAK No', 'User', 'No. MSISDN'])
            ->assertJsonPath('data', [
                ['line' => 4, 'cells' => ['BAK/2', 'Eka <b>Putra</b>', '0800000222']], // markup is data: the page escapes it
                ['line' => 3, 'cells' => ['BAK/1', 'Budi Santoso', '0800000111']],
            ]);
        $this->rows(['sheet' => 2])->assertOk()->assertJsonPath('sheet.columns', [])->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
        // A sheet number that is not there is the nearest one that is.
        $this->rows(['sheet' => 99])->assertOk()->assertJsonPath('sheet.index', 2);
        $this->rows(['sheet' => -3])->assertOk()->assertJsonPath('sheet.index', 0);
        Http::assertSentCount(4);

        // The address of the workbook in SharePoint comes from Graph: it is handed to the page only when it is an https address.
        foreach (['javascript:alert(1)', 'http://contoso.sharepoint.com/Doc.aspx', 7] as $odd) {
            Cache::flush();
            $this->microsoft['item'] = fn () => Http::response($this->item(['webUrl' => $odd]));
            $this->rows()->assertOk()->assertJsonPath('workbook.web_url', null);
        }
    }

    public function test_a_search_counts_matches_on_every_sheet_and_sorting_knows_numbers_and_dates(): void
    {
        $this->actingAs($this->superAdmin());
        $first = fn (array $query) => array_map(fn (array $row) => $row['cells'][0], $this->rows($query)->assertOk()->json('data'));

        // Every word typed must be somewhere in the row, in any column and any case.
        $this->rows(['search' => 'BUDI'])->assertOk()
            ->assertJsonPath('sheets.*.matches', [1, 1, 0])
            ->assertJsonPath('data.*.cells.0', ['ICT/SRF/1'])
            ->assertJsonPath('meta.total', 1);
        $this->rows(['search' => 'budi', 'sheet' => 1])->assertOk()->assertJsonPath('data.*.cells.0', ['BAK/1']);
        $this->assertSame(['ICT/SRF/2'], $first(['search' => '  sn-9   wijaya ']));
        $this->assertSame(['ICT/SRF/3', 'ICT/SRF/2'], $first(['search' => '03-2023 sn-'])); // a date as it is shown
        // A word does not run from one cell into the next ("ICT/SRF/1" ends one cell, "15-03-2023" begins the next).
        $this->rows(['search' => 'srf/115-03'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->rows(['search' => 'srf/1 15-03'])->assertOk()->assertJsonPath('meta.total', 1);
        // No more than 100 characters of what is typed are used: the words after that are not looked for.
        $this->rows(['search' => 'budi' . str_repeat(' ', 100) . 'tidakada'])->assertOk()->assertJsonPath('meta.total', 1);
        $this->rows(['search' => 'budi' . str_repeat(' ', 90) . 'tidakada'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->rows(['search' => 'tidak ada yang begini'])->assertOk()
            ->assertJsonPath('sheets.*.matches', [0, 0, 0])
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta', ['current_page' => 1, 'from' => null, 'last_page' => 1, 'per_page' => 25, 'to' => null, 'total' => 0]);

        // The order of the workbook, either way; another value for "sort" means that order too.
        $this->assertSame(['ICT/SRF/3', 'ICT/SRF/10', 'ICT/SRF/2', 'ICT/SRF/1'], $first([]));
        $this->assertSame(['ICT/SRF/1', 'ICT/SRF/2', 'ICT/SRF/10', 'ICT/SRF/3'], $first(['sort' => 'row', 'dir' => 'asc']));
        $this->assertSame(['ICT/SRF/3', 'ICT/SRF/10', 'ICT/SRF/2', 'ICT/SRF/1'], $first(['sort' => 'c99']));
        $this->rows(['sort' => 'c99'])->assertOk()->assertJsonPath('sheet.sort', 'row')->assertJsonPath('sheet.dir', 'desc');
        // Text in natural order: SRF/2 before SRF/10.
        $this->assertSame(['ICT/SRF/1', 'ICT/SRF/2', 'ICT/SRF/3', 'ICT/SRF/10'], $first(['sort' => 'c0']));
        $this->assertSame(['ICT/SRF/10', 'ICT/SRF/3', 'ICT/SRF/2', 'ICT/SRF/1'], $first(['sort' => 'c0', 'dir' => 'desc']));
        // Numbers by their value (1, 2, 10), and empty cells last whichever way it goes.
        $this->assertSame(['ICT/SRF/3', 'ICT/SRF/1', 'ICT/SRF/2', 'ICT/SRF/10'], $first(['sort' => 'c3']));
        $this->assertSame(['ICT/SRF/2', 'ICT/SRF/1', 'ICT/SRF/3', 'ICT/SRF/10'], $first(['sort' => 'c3', 'dir' => 'desc']));
        // Numbers by their value, not as the text they are written as: 250.000,50 is less than 1.500.000.
        $this->assertSame(['ICT/SRF/2', 'ICT/SRF/1', 'ICT/SRF/10', 'ICT/SRF/3'], $first(['sort' => 'c4']));
        $this->assertSame(['ICT/SRF/1', 'ICT/SRF/2', 'ICT/SRF/10', 'ICT/SRF/3'], $first(['sort' => 'c4', 'dir' => 'desc']));
        // Dates by the day, not by how they are written.
        $this->assertSame(['ICT/SRF/3', 'ICT/SRF/1', 'ICT/SRF/2', 'ICT/SRF/10'], $first(['sort' => 'c1']));
        $this->assertSame(['ICT/SRF/2', 'ICT/SRF/1', 'ICT/SRF/3', 'ICT/SRF/10'], $first(['sort' => 'c1', 'dir' => 'desc']));
        // A serial number is text even when it is all digits: "000123", then SN-9 before sn-10.
        $this->assertSame(['ICT/SRF/1', 'ICT/SRF/2', 'ICT/SRF/3', 'ICT/SRF/10'], $first(['sort' => 'c5']));
        $this->rows(['sort' => 'c3'])->assertOk()->assertJsonPath('sheet.sort', 'c3')->assertJsonPath('sheet.dir', 'asc');

        $this->rows(['sort' => 'c0', 'paginated' => 3, 'page' => 2])->assertOk()
            ->assertJsonPath('data.*.cells.0', ['ICT/SRF/10'])
            ->assertJsonPath('meta', ['current_page' => 2, 'from' => 4, 'last_page' => 2, 'per_page' => 3, 'to' => 4, 'total' => 4]);
        $this->rows(['paginated' => 3, 'page' => 99])->assertOk()->assertJsonPath('meta.current_page', 2);
        $this->rows(['paginated' => 1000, 'page' => -1])->assertOk()->assertJsonPath('meta.per_page', 100)->assertJsonPath('meta.current_page', 1);

        $this->assertSame(1, $this->sent(self::DOWNLOAD), 'The workbook is read once, the rest works on what is kept.');
    }

    public function test_what_was_read_is_kept_and_an_unchanged_workbook_is_not_downloaded_again(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));

        $this->rows()->assertOk();
        $this->rows(['search' => 'budi'])->assertOk();
        $this->assertSame([1, 1], [$this->sent('/shares/'), $this->sent(self::DOWNLOAD)]);

        // "Muat ulang" straight away: what is kept is seconds old and is used again.
        $this->travel(5)->seconds();
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('workbook.fetched_at', '07-10-2026 10:00');
        $this->assertSame(1, $this->sent('/shares/'));

        // Later it asks SharePoint, and when the file has not been saved since, that is all it does.
        $this->travel(15)->seconds();
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('meta.total', 4);
        $this->assertSame([2, 1], [$this->sent('/shares/'), $this->sent(self::DOWNLOAD)]);
        $this->travel(11)->minutes();
        $this->rows()->assertOk()->assertJsonPath('workbook.fetched_at', '07-10-2026 10:11');
        $this->assertSame([3, 1], [$this->sent('/shares/'), $this->sent(self::DOWNLOAD)]);

        // A saved change: the workbook is downloaded and read again.
        $this->bytes = self::xlsx(['SRF' => [['SRF No', 'User'], ['ICT/SRF/99', 'Baru Datang']]]);
        $this->version = '"{BOOK},2"';
        $this->travel(11)->minutes();
        $this->rows()->assertOk()->assertJsonPath('data.*.cells', [['ICT/SRF/99', 'Baru Datang']])->assertJsonPath('sheets.*.name', ['SRF']);
        $this->assertSame([4, 2], [$this->sent('/shares/'), $this->sent(self::DOWNLOAD)]);

        // Asking for other sheets in the config is a reason to read the workbook again, saved or not, and at once.
        config(['sharepoint-docs.workbooks.ict-masterlist.sheets' => ['SRF']]);
        $this->rows()->assertOk();
        $this->assertSame(3, $this->sent(self::DOWNLOAD));

        // What another version of the application kept is not used. (It is kept per address and per choice of sheets and rows.)
        $key = 'sharepoint-docs:book:' . sha1(self::ADDRESS . "\nevery row\nSRF");
        $this->assertSame(2, Cache::get($key)['format'] ?? null, 'This is where a workbook is kept, with its format.');
        Cache::put($key, ['fetched_at' => now()->getTimestamp(), 'sheets' => [['name' => 'Lama']]], 3600);
        $this->rows()->assertOk()->assertJsonPath('sheets.*.name', ['SRF']);
        $this->assertSame(4, $this->sent(self::DOWNLOAD));

        // A copy dated in the future (the server clock was set back) is not taken for a fresh one.
        $this->travel(-2)->hours();
        $shares = $this->sent('/shares/');
        $this->rows()->assertOk();
        $this->assertSame($shares + 1, $this->sent('/shares/'));
    }

    public function test_a_change_of_the_sheets_that_are_shown_is_in_force_at_once_and_never_shows_the_old_choice(): void
    {
        $this->actingAs($this->superAdmin());
        $choice = fn (?array $sheets) => config(['sharepoint-docs.workbooks.ict-masterlist.sheets' => $sheets]);
        $this->rows()->assertOk()->assertJsonPath('sheets.*.name', ['SRF', 'Kartu HALO', 'Kosong']);

        // No waiting for what is kept to grow old.
        $choice(['Kosong', 'SRF']);
        $this->rows()->assertOk()->assertJsonPath('sheets.*.name', ['Kosong', 'SRF']);

        // And no falling back on the other choice while Microsoft cannot be reached: nothing is kept for this one.
        $choice(['Kartu HALO']);
        $this->microsoft['item'] = fn () => Http::failedConnection();
        $this->rows()->assertStatus(503)->assertJsonMissingPath('sheets');

        // What was kept for each choice is still there for it.
        $choice(null);
        $this->rows()->assertOk()->assertJsonPath('sheets.*.name', ['SRF', 'Kartu HALO', 'Kosong'])->assertJsonPath('workbook.stale', false);
        $choice(['Kosong', 'SRF']);
        $this->rows()->assertOk()->assertJsonPath('sheets.*.name', ['Kosong', 'SRF'])->assertJsonPath('workbook.stale', false);
    }

    public function test_bytes_that_are_not_the_workbook_do_not_throw_away_what_was_kept(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false);

        // The workbook was saved since, and what comes back when its bytes are asked for is the page of a web filter.
        $this->travel(11)->minutes();
        $this->version = '"{BOOK},2"';
        $this->microsoft['download'] = fn () => Http::response('<html>Sign in to the network</html>');
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true)->assertJsonPath('meta.total', 4);
        // It is not asked again for a minute, and the next try after that gets the file.
        $asked = Http::recorded()->count();
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('workbook.stale', true);
        $this->assertSame($asked, Http::recorded()->count());
        $this->travel(61)->seconds();
        $this->microsoft['download'] = fn () => Http::response($this->bytes);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false);

        // A workbook that really is unreadable (too many cells) is another matter: that is the file, and what was kept goes.
        $this->travel(11)->minutes();
        $this->version = '"{BOOK},3"';
        config(['sharepoint-docs.workbook_max_cells' => 3]);
        $this->rows()->assertStatus(413)->assertJsonPath('reason', 'too_large');
        $this->rows()->assertStatus(413);
    }

    public function test_a_workbook_that_cannot_be_read_is_not_downloaded_again_until_it_changes(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        config(['sharepoint-docs.workbook_max_cells' => 3]);

        $this->rows()->assertStatus(413)->assertJsonPath('reason', 'too_large');
        $this->assertSame(1, $this->sent(self::DOWNLOAD));
        // Asked again, in whatever way: the same answer, and SharePoint is only asked which version the file is.
        $this->rows()->assertStatus(413);
        $this->rows(['refresh' => 1])->assertStatus(413);
        $this->rows(['search' => 'budi'])->assertStatus(413);
        $this->assertSame([1, 4], [$this->sent(self::DOWNLOAD), $this->sent('/shares/')]);

        // A saved version is read again ...
        $this->bytes = self::xlsx(['SRF' => [['No', 'User'], ['1']]]);
        $this->version = '"{BOOK},2"';
        $this->rows()->assertOk()->assertJsonPath('sheets.*.name', ['SRF']);
        $this->assertSame(2, $this->sent(self::DOWNLOAD));

        // ... and so is a version that failed, when what decided it has changed (a limit that was raised: at once) or
        // the time to try again has come (the same answer, which is noted again).
        $this->bytes = self::xlsx(self::book());
        $this->version = '"{BOOK},3"';
        $this->travel(11)->minutes();
        $this->rows()->assertStatus(413);
        $this->rows()->assertStatus(413);
        $this->assertSame(3, $this->sent(self::DOWNLOAD));
        $this->travel(11)->minutes();
        $this->rows()->assertStatus(413);
        $this->assertSame(4, $this->sent(self::DOWNLOAD));
        config(['sharepoint-docs.workbook_max_cells' => 1000]);
        $this->rows()->assertOk();
        $this->assertSame(5, $this->sent(self::DOWNLOAD));

        // The workbook is read; what failed before is forgotten with it. Were the copy that was kept gone too (a day
        // has passed), the limit as it was before would not refuse the workbook from memory: it is read again.
        Cache::forget('sharepoint-docs:book:' . sha1(self::ADDRESS . "\nevery row\n*"));
        config(['sharepoint-docs.workbook_max_cells' => 3]);
        $this->rows()->assertStatus(413);
        $this->assertSame(6, $this->sent(self::DOWNLOAD));
    }

    public function test_a_workbook_that_is_not_one_is_not_downloaded_again_either_and_what_decided_it_is_what_counts(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $this->bytes = "PK\x03\x04" . str_repeat('x', 200); // zip's first bytes, and nothing of a zip behind them
        $this->version = '"{BOOK},1"';

        $this->rows()->assertStatus(415)->assertJsonPath('reason', 'unsupported');
        $this->rows()->assertStatus(415);
        $this->rows(['refresh' => 1])->assertStatus(415);
        $this->assertSame(1, $this->sent(self::DOWNLOAD));

        // Another choice of sheets is another reading: it is not refused for what was found with the other.
        config(['sharepoint-docs.workbooks.ict-masterlist.sheets' => ['SRF']]);
        $this->rows()->assertStatus(415);
        $this->assertSame(2, $this->sent(self::DOWNLOAD));

        // The time until it is tried again is that of the setting as it is now, also when it was longer when the answer was noted.
        config(['sharepoint-docs.cache_minutes' => 60]);
        $this->version = '"{BOOK},2"';
        $this->rows()->assertStatus(415);
        $this->travel(11)->minutes();
        $this->rows()->assertStatus(415);
        $this->assertSame(3, $this->sent(self::DOWNLOAD));
        config(['sharepoint-docs.cache_minutes' => 10]);
        $this->rows()->assertStatus(415);
        $this->assertSame(4, $this->sent(self::DOWNLOAD));
    }

    /**
     * The reader as it is, but it says what time it was given, and while $state['slow'] it runs out of it.
     *
     * @param array{slow: bool, seconds: array<int, float>} $state
     */
    private function readerThatCanRunOutOfTime(array &$state): void
    {
        $this->app->bind(XlsxReader::class, function () use (&$state) {
            return new class($state) extends XlsxReader
            {
                public function __construct(private array &$state)
                {
                }

                public function sheets(string $file, int $maxRows, int $maxColumns, int $maxCells = self::MAX_CELLS, float $seconds = 15.0): Generator
                {
                    $this->state['seconds'][] = $seconds;
                    if ($this->state['slow']) {
                        throw new GraphException(GraphException::TOO_SLOW, 'Reading the workbook took longer than the time allowed for it.');
                    }

                    yield from parent::sheets($file, $maxRows, $maxColumns, $maxCells, $seconds);
                }
            };
        });
    }

    public function test_a_reading_that_takes_too_long_is_a_slow_reading_and_no_verdict_on_the_workbook(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $state = ['slow' => false, 'seconds' => []];
        $this->readerThatCanRunOutOfTime($state);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false);

        // A saved version that cannot be read in the time a reading may take: what was kept is shown, as old, and is not thrown away.
        $this->travel(11)->minutes();
        $this->version = '"{BOOK},2"';
        $state['slow'] = true;
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true)->assertJsonPath('meta.total', 4);
        $this->assertEqualsWithDelta([15.0, 15.0], $state['seconds'], 0.001);
        // It had all the time a reading may have: the workbook is too heavy for this server, and not tried again before the time to do so.
        $downloads = $this->sent(self::DOWNLOAD);
        $this->travel(61)->seconds();
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('workbook.stale', true);
        $this->assertSame($downloads, $this->sent(self::DOWNLOAD));
        $this->travel(11)->minutes();
        $state['slow'] = false;
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false);
        $this->assertSame($downloads + 1, $this->sent(self::DOWNLOAD));

        // With nothing kept there is nothing to show: the page says that it took too long, not that the workbook is too large.
        Cache::flush();
        $state['slow'] = true;
        $this->version = '"{BOOK},3"';
        $response = $this->rows()->assertStatus(504)->assertJsonPath('reason', 'too_slow');
        $this->assertStringContainsString('dibaca', $response->json('message'));
        $this->assertStringNotContainsString('terlalu besar', $response->json('message'));
    }

    public function test_a_reading_that_had_less_time_than_a_reading_may_have_proves_nothing_about_the_workbook(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        config(['sharepoint-docs.preview_time_limit' => 8]);
        $state = ['slow' => false, 'seconds' => []];
        $this->readerThatCanRunOutOfTime($state);
        $this->rows()->assertOk();

        $this->travel(11)->minutes();
        $this->version = '"{BOOK},2"';
        $state['slow'] = true;
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true);
        $this->assertEqualsWithDelta([8.0, 8.0], $state['seconds'], 0.001, 'What is left of the 8 seconds, as much as the download left.');

        // It is held back for a minute like any slowness, and then read again, not refused for what was found this time.
        $downloads = $this->sent(self::DOWNLOAD);
        $this->travel(61)->seconds();
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('workbook.stale', true);
        $this->assertSame($downloads + 1, $this->sent(self::DOWNLOAD));
    }

    public function test_a_slow_download_stays_a_slow_download_when_asked_again_within_the_minute(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $this->microsoft['download'] = self::transfer(500_000, 100_000, self::timeout());

        $this->rows()->assertStatus(504)->assertJsonPath('reason', 'too_slow');
        $asked = Http::recorded()->count();
        $this->rows()->assertStatus(504)->assertJsonPath('reason', 'too_slow');
        $this->rows(['refresh' => 1])->assertStatus(504)->assertJsonPath('reason', 'too_slow');
        $this->assertSame($asked, Http::recorded()->count());

        // An outage stays an outage.
        Cache::flush();
        $this->microsoft['item'] = fn () => Http::failedConnection();
        $this->rows()->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->rows()->assertStatus(503)->assertJsonPath('reason', 'unavailable');

        // After the minute SharePoint is asked again, and a copy kept meanwhile is shown as old while it is not.
        $this->travel(61)->seconds();
        $this->microsoft = array_merge($this->microsoft, ['item' => fn () => Http::response($this->item()), 'download' => fn () => Http::response($this->bytes)]);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false);
    }

    public function test_reading_a_workbook_has_one_time_limit_for_everything_it_needs(): void
    {
        $this->actingAs($this->superAdmin());
        $this->freezeSecond();
        config(['sharepoint-docs.preview_time_limit' => 3]);
        $prompt = $this->microsoft;
        $this->microsoft = array_map(fn (callable $answer) => $this->after(0.5, $answer), $prompt);

        $this->rows()->assertOk();

        // The sign-in, the item, Graph's pointer and the bytes each get what is left of the three seconds.
        $this->assertSame(
            ['login.microsoftonline.com', '/shares/', '/content', '/download.aspx'],
            array_map(fn (array $sent) => preg_match('#login\.microsoftonline\.com|/shares/|/content|/download\.aspx#', $sent[0], $part) === 1 ? $part[0] : $sent[0], $this->sentWith)
        );
        $this->assertEqualsWithDelta([3.0, 2.5, 2.0, 1.5], array_map(fn (array $sent) => (float) $sent[1]['timeout'], $this->sentWith), 0.001);
    }

    public function test_the_reading_of_the_workbook_gets_the_limits_of_the_settings_and_what_is_left_of_the_time(): void
    {
        $this->actingAs($this->superAdmin());
        $this->freezeSecond();
        // The reader as it is, but it says what it was asked for.
        $seen = [];
        $this->app->bind(XlsxReader::class, function () use (&$seen) {
            return new class($seen) extends XlsxReader
            {
                public function __construct(private array &$seen)
                {
                }

                public function sheets(string $file, int $maxRows, int $maxColumns, int $maxCells = self::MAX_CELLS, float $seconds = 15.0): Generator
                {
                    $this->seen[] = [$maxCells, $seconds];

                    yield from parent::sheets($file, $maxRows, $maxColumns, $maxCells, $seconds);
                }
            };
        });

        // The cells that may be read are a setting, and the time is what is left of the 25 seconds, between 5 and 15.
        config(['sharepoint-docs.workbook_max_cells' => 12345]);
        $this->rows()->assertOk();
        $this->assertEqualsWithDelta([[12345, 15.0]], $seen, 0.001);

        $seen = [];
        Cache::flush();
        $this->microsoft['download'] = $this->after(18, $this->microsoft['download']);
        $this->rows()->assertOk();
        $this->assertEqualsWithDelta([[12345, 7.0]], $seen, 0.5);

        $seen = [];
        Cache::flush();
        $this->microsoft['download'] = $this->after(23.5, fn () => Http::response($this->bytes));
        $this->rows()->assertOk();
        $this->assertEqualsWithDelta([[12345, 5.0]], $seen, 0.001);
    }

    public function test_rows_numbered_ahead_of_use_are_left_out_when_the_config_says_so(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $scan = fn (int $row) => ['formula' => "=\"https://contoso.example/scan/\"&A{$row}"];
        $this->bytes = self::xlsx(['Register' => [
            ['No', 'Date', 'User', 'Scan'],
            ['REG/1', ['value' => 45000, 'format' => 'd-mmm-yy'], 'Budi Santoso', $scan(2)],
            ['REG/2', null, 'Andi Wijaya', $scan(3)], // no date yet, but in use
            ['REG/3', null, null, $scan(4)], // nothing but its number, and what a formula makes of it
            ['REG/4', null, null, $scan(5)],
        ]]);

        // Without the setting every row is shown.
        $this->rows()->assertOk()->assertJsonPath('sheets.0.rows', 4)->assertJsonPath('data.*.line', [5, 4, 3, 2]);

        // With it: the rows that are in use, under the numbers they have in Excel. (The workbook
        // was not saved in between, but what is kept was made with the other choice: it is read again.)
        config(['sharepoint-docs.workbooks.ict-masterlist.skip_unused_rows' => true]);
        $this->travel(11)->minutes();
        $this->rows()->assertOk()
            ->assertJsonPath('sheets.0.rows', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data', [
                ['line' => 3, 'cells' => ['REG/2', '', 'Andi Wijaya', 'https://contoso.example/scan/REG/2']],
                ['line' => 2, 'cells' => ['REG/1', '15-03-2023', 'Budi Santoso', 'https://contoso.example/scan/REG/1']],
            ]);
        $this->assertSame(2, $this->sent(self::DOWNLOAD));
        // The choice has not changed and neither has the workbook: what is kept is used.
        $this->travel(11)->minutes();
        $this->rows()->assertOk()->assertJsonPath('sheets.0.rows', 2);
        $this->assertSame(2, $this->sent(self::DOWNLOAD));
    }

    public function test_sheets_are_shown_as_the_config_says_and_a_very_wide_sheet_is_cut_off(): void
    {
        $this->actingAs($this->superAdmin());

        // Named sheets, in that order, the hidden one included; a name that is not there is passed over.
        config(['sharepoint-docs.workbooks.ict-masterlist.sheets' => ['loan it', 'Tidak Ada', 'SRF']]);
        $this->rows()->assertOk()
            ->assertJsonPath('sheets.*.name', ['Loan IT', 'SRF'])
            ->assertJsonPath('sheet.columns', ['LOA No', 'User', 'Device'])
            ->assertJsonPath('data.*.cells.0', ['LOA/1']);

        // More columns than are read: cut off, and said so. A column without a title gets its letter.
        Cache::flush();
        config(['sharepoint-docs.workbooks.ict-masterlist.sheets' => null]);
        $wide = array_map(fn (int $n) => "K{$n}", range(1, WorkbookReader::MAX_COLUMNS + 5));
        $wide[1] = null;
        $this->bytes = self::xlsx(['Lebar' => [$wide, array_fill(0, WorkbookReader::MAX_COLUMNS + 5, 'x')]]);
        $this->version = '"{BOOK},9"';
        $sheet = $this->rows()->assertOk()->assertJsonPath('sheet.truncated', true)->json('sheet');
        $this->assertCount(WorkbookReader::MAX_COLUMNS, $sheet['columns']);
        $this->assertSame(['K1', 'Kolom B', 'K3'], array_slice($sheet['columns'], 0, 3));
    }

    public function test_when_microsoft_cannot_be_reached_what_is_kept_is_shown_as_old(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', false);

        $this->travel(11)->minutes();
        $this->microsoft['item'] = fn () => Http::failedConnection();
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true)->assertJsonPath('workbook.fetched_at', '07-10-2026 10:00')->assertJsonPath('meta.total', 4);
        // For the next minute nobody waits for Microsoft again, not even with "Muat ulang".
        $asked = Http::recorded()->count();
        $this->rows(['refresh' => 1])->assertOk()->assertJsonPath('workbook.stale', true);
        $this->assertSame($asked, Http::recorded()->count());

        // A web filter's block page is not Microsoft withdrawing the permission: what is kept stays.
        $this->travel(61)->seconds();
        $this->microsoft['item'] = fn () => Http::response('<html>Blocked by policy</html>', 403);
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true);

        // The workbook was saved, but its new bytes do not arrive in time: still what is kept.
        $this->travel(61)->seconds();
        $this->microsoft['item'] = fn () => Http::response($this->item(['eTag' => '"{BOOK},2"']));
        $this->microsoft['download'] = self::transfer(500_000, 100_000, self::timeout());
        $this->rows()->assertOk()->assertJsonPath('workbook.stale', true)->assertJsonPath('meta.total', 4);

        // Microsoft's own refusal shows at once, and what was kept does not come back at the next hiccup.
        $this->travel(61)->seconds();
        $this->microsoft['item'] = self::graphError(403, 'accessDenied');
        $this->rows()->assertStatus(502)->assertJsonPath('reason', 'forbidden');
        $this->microsoft['item'] = fn () => Http::failedConnection();
        $this->rows()->assertStatus(503)->assertJsonPath('reason', 'unavailable')->assertJsonMissingPath('data');
        // Nothing kept: the failure is repeated for a minute without asking again.
        $asked = Http::recorded()->count();
        $this->rows(['refresh' => 1])->assertStatus(503);
        $this->assertSame($asked, Http::recorded()->count());
    }

    public function test_a_failure_is_explained_without_repeating_what_microsoft_said(): void
    {
        $this->actingAs($this->superAdmin());
        $small = fn () => Http::response($this->item(['size' => 16 * 1024 * 1024]));

        $cases = [
            // what goes wrong => [who answers, the answer, status of the page, reason, a word of the message]
            'wrong secret' => ['token', fn () => Http::response(['error' => 'invalid_client', 'error_description' => 'AADSTS7000215: Invalid client secret.'], 401), 502, 'credentials', 'client secret'],
            'no permission' => ['item', self::graphError(403, 'accessDenied'), 502, 'forbidden', 'Sites.Read.All'],
            'the workbook is gone' => ['item', self::graphError(404, 'itemNotFound'), 404, 'not_found', 'Workbook tidak ditemukan'],
            'the address is not in SharePoint' => ['item', self::graphError(400, 'invalidRequest'), 404, 'not_found', 'Workbook tidak ditemukan'],
            'the address is a folder' => ['item', fn () => Http::response(['id' => 'F1', 'name' => 'Records', 'folder' => ['childCount' => 2], 'parentReference' => ['driveId' => 'b!drive-1']]), 415, 'unsupported', 'bukan workbook Excel'],
            'the address is another kind of file' => ['item', fn () => Http::response($this->item(['name' => 'Daftar Induk.xls'])), 415, 'unsupported', 'bukan workbook Excel'],
            'the workbook is too large' => ['item', $small, 413, 'too_large', 'terlalu besar'],
            // What something in between sends instead of the file is not a verdict on the file: "unavailable".
            'its bytes are not a workbook' => ['download', fn () => Http::response('<html>Sign in</html>'), 503, 'unavailable', 'tidak bisa dihubungi'],
            'its bytes are nothing at all' => ['download', fn () => Http::response(''), 503, 'unavailable', 'tidak bisa dihubungi'],
            'its bytes are a workbook with a password' => ['download', fn () => Http::response("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 200)), 415, 'unsupported', 'password'],
            'its bytes are a broken workbook' => ['download', fn () => Http::response("PK\x03\x04" . str_repeat('x', 200)), 415, 'unsupported', 'bukan workbook Excel'],
            'its ids cannot be used' => ['item', fn () => Http::response($this->item(['id' => '../../me'])), 503, 'unavailable', 'tidak bisa dihubungi'],
            'removed between the two requests' => ['content', self::graphError(404, 'itemNotFound'), 404, 'not_found', 'Workbook tidak ditemukan'],
            'graph is down' => ['item', self::graphError(500, 'generalException'), 503, 'unavailable', 'tidak bisa dihubungi'],
            'no connection' => ['item', fn () => Http::failedConnection(), 503, 'unavailable', 'tidak bisa dihubungi'],
            'the bytes come too slowly' => ['download', self::transfer(500_000, 100_000, self::timeout()), 504, 'too_slow', 'terlalu lama'],
            'the connection is cut while the bytes come' => ['download', self::transfer(500_000, 100_000, self::connectionReset()), 503, 'unavailable', 'tidak bisa dihubungi'],
        ];

        $working = $this->microsoft;
        foreach ($cases as $case => [$part, $answer, $status, $reason, $word]) {
            Cache::flush();
            $this->microsoft = [$part => $answer] + $working;

            $response = $this->rows();

            $this->assertSame([$status, $reason], [$response->getStatusCode(), $response->json('reason')], $case);
            $this->assertSame(['message', 'reason'], array_keys($response->json()), $case);
            $this->assertStringContainsString($word, $response->json('message'), $case);
            // The workbook's address is not on the page, so nothing may send the reader to a button that is not there.
            $this->assertStringNotContainsString('Buka di Excel', $response->json('message'), $case);
            foreach (['secret-1', 'token-1', 'AADSTS', 'Microsoft says', 'tempauth', 'Sign in'] as $private) {
                $this->assertStringNotContainsString($private, $response->getContent(), $case);
            }
        }
    }

    public function test_the_menu_decides_who_reads_the_workbook(): void
    {
        $this->get(route('documents.ict-masterlist.index'))->assertRedirect(route('login'));
        $this->getJson(route('documents.ict-masterlist.rows'))->assertUnauthorized();

        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio');
        $viewer = $this->makeUser('viewer.radio@test.local', 'Viewer Radio');

        // In the sidebar: its own section, below "COF Scan" and above "User Management".
        $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()
            ->assertSeeInOrder(['Import Excel', 'Dokumen', 'COF Scan', 'ICT Masterlist', 'User Management']);
        $this->actingAs($admin)->get(route('dashboard.index'))->assertOk()->assertSeeInOrder(['COF Scan', 'ICT Masterlist']);
        $this->actingAs($admin)->get(route('documents.ict-masterlist.index'))->assertOk();
        $this->actingAs($admin)->getJson(route('documents.ict-masterlist.rows'))->assertOk()->assertJsonPath('meta.total', 4);

        // Viewer Radio is not given the workbook by default.
        $this->actingAs($viewer)->get(route('dashboard.index'))->assertOk()->assertDontSee('ICT Masterlist');
        $this->actingAs($viewer)->get(route('documents.ict-masterlist.index'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('documents.ict-masterlist.rows'))->assertForbidden();

        // Ticked for the role without Read: the page opens, its rows stay closed.
        $grant = [
            'menu_id' => DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->value('id'),
            'role_id' => DB::table('roles')->where('name', 'Viewer Radio')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('menu_role')->insert($grant);
        $this->actingAs($viewer->fresh())->get(route('documents.ict-masterlist.index'))->assertOk();
        $this->actingAs($viewer->fresh())->getJson(route('documents.ict-masterlist.rows'))->assertForbidden();
        DB::table('menu_role_permission')->insert($grant + ['permission_id' => CheckMenuPermission::PERMISSION_IDS['Read']]);
        $this->actingAs($viewer->fresh())->getJson(route('documents.ict-masterlist.rows'))->assertOk();

        // Without its menu row the page is closed, not open to everyone.
        (require database_path(self::MIGRATION))->down();
        $this->actingAs($admin->fresh())->get(route('documents.ict-masterlist.index'))->assertForbidden();
        $this->actingAs($admin->fresh())->getJson(route('documents.ict-masterlist.rows'))->assertForbidden();
        $this->actingAs($this->superAdmin())->getJson(route('documents.ict-masterlist.rows'))->assertOk();
    }

    public function test_the_page_says_so_when_the_workbook_or_the_application_is_not_configured(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['sharepoint-docs.workbooks.ict-masterlist.url' => '  ', 'services.azure.client_secret' => null] as $key => $missing) {
            $configured = config($key);
            config([$key => $missing]);

            $this->get(route('documents.ict-masterlist.index'))->assertOk()->assertSee('belum bisa ditampilkan')->assertDontSee('wbBook');
            $this->rows()->assertStatus(503)->assertJsonPath('reason', 'not_configured');

            config([$key => $configured]);
        }

        Http::assertNothingSent();
    }

    public function test_the_menu_is_added_below_cof_scan_and_makes_room_for_itself(): void
    {
        $order = fn () => DB::table('menu')->whereNull('parent_id')->whereIn('title', ['COF Scan', 'ICT Masterlist', 'User Management', 'Approval Management'])
            ->orderBy('order')->pluck('order', 'title')->map(fn ($place) => (int) $place)->all();
        $menu = DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->sole();
        $this->assertSame(['ICT Masterlist', 'Dokumen', 'table', null], [$menu->title, $menu->section, $menu->icon, $menu->parent_id]);
        $this->assertSame(['COF Scan' => 7, 'ICT Masterlist' => 8, 'User Management' => 9, 'Approval Management' => 10], $order());
        $this->assertSame('/documents/ict-masterlist', route(IctMasterlistMenuSeeder::ROUTE, [], false));
        $this->assertSame('/documents/ict-masterlist/rows', route('documents.ict-masterlist.rows', [], false));

        $read = CheckMenuPermission::PERMISSION_IDS['Read'];
        $adminRole = (int) DB::table('roles')->where('name', 'Admin Radio')->value('id');
        $grants = fn (string $route) => DB::table('menu_role_permission')
            ->where('menu_id', DB::table('menu')->where('route', $route)->value('id'))->orderBy('role_id')->get()
            ->map(fn ($grant) => [(int) $grant->role_id, (int) $grant->permission_id])->all();
        // Super Admin and Admin Radio, read only; not Viewer Radio.
        $this->assertSame([[1, $read], [$adminRole, $read]], $grants(IctMasterlistMenuSeeder::ROUTE));

        // A database in use as it was before this menu: the migration adds it and moves the two entries below it down.
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertSame(['COF Scan' => 7, 'User Management' => 8, 'Approval Management' => 9], $order());
        $migration->up();
        $this->assertSame(['COF Scan' => 7, 'ICT Masterlist' => 8, 'User Management' => 9, 'Approval Management' => 10], $order());
        $this->assertSame([[1, $read], [$adminRole, $read]], $grants(IctMasterlistMenuSeeder::ROUTE));

        // The owner moves things: migrating or seeding again leaves the menu and their order alone.
        DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->update(['title' => 'Register ICT', 'order' => 2]);
        DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->update(['order' => 30]);
        $migration->up();
        $this->seed(IctMasterlistMenuSeeder::class);
        $this->assertSame('Register ICT', DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->value('title'));
        $this->assertSame(30, (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order'));
        // Rolled back from there, an entry that is no longer where the seeder put it is not moved.
        $migration->down();
        $this->assertSame(30, (int) DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order'));

        // A base-app database gets the radio roles after the menus: Admin Radio is still given both documents.
        $migration->up();
        $radioRoles = DB::table('roles')->whereIn('name', ['Admin Radio', 'Viewer Radio'])->pluck('id');
        DB::table('menu_role_permission')->whereIn('role_id', $radioRoles)->delete();
        DB::table('menu_role')->whereIn('role_id', $radioRoles)->delete();
        DB::table('roles')->whereIn('id', $radioRoles)->delete();
        $this->seed(RadioMenuSeeder::class);
        $adminRole = (int) DB::table('roles')->where('name', 'Admin Radio')->value('id');
        $this->assertSame([[1, $read], [$adminRole, $read]], $grants(IctMasterlistMenuSeeder::ROUTE));
        $this->assertContains([$adminRole, $read], $grants(CofScanMenuSeeder::ROUTE));
    }
}
