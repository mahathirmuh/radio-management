<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use Database\Seeders\CofScanMenuSeeder;
use Database\Seeders\RadioMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as GraphRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Modules\SharePointDocs\Services\GraphClient;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\SharePointFolder;
use Tests\Concerns\MakesTransfers;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * The "COF Scan" page: a SharePoint folder read through Microsoft Graph. Microsoft is played by
 * Http::fake(); every tenant, name and address here is made up.
 */
class SharePointDocsTest extends TestCase
{
    use MakesTransfers;
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    /** The link as it is copied from the browser: a library view with the folder in ?id=. */
    private const ADDRESS = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Forms/AllItems.aspx?id=%2Fsites%2FTeam%2FShared%20Documents%2FScans&viewid=1';

    private const FOLDER_URL = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Scans';

    private const MIGRATION = 'migrations/2026_10_07_100000_add_cof_scan_menu.php';

    /** Where the pretend Graph says a file's bytes are (a one-time address at SharePoint). */
    private const DOWNLOAD = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/download.aspx';

    private const PDF = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    /** @var array<string, callable> what the pretend Microsoft answers: 'token', 'root', 'children', 'content', 'download' */
    private array $microsoft = [];

    /** @var array<int, array{0: mixed, 1: mixed}> seconds every request was given: to connect, and in all */
    private array $limits = [];

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
            'sharepoint-docs.folders.cof-scan.url' => self::ADDRESS,
            'sharepoint-docs.cache_minutes' => 10,
            'app.timezone' => 'Asia/Makassar',
        ]);

        $this->microsoft = [
            'token' => fn () => Http::response(['token_type' => 'Bearer', 'expires_in' => 3599, 'access_token' => 'token-1']),
            'root' => fn () => Http::response([
                'id' => 'ROOT1',
                'name' => 'Scans',
                'webUrl' => self::FOLDER_URL,
                'folder' => ['childCount' => 3],
                'parentReference' => ['driveId' => 'b!drive-1'],
            ]),
            'children' => fn () => Http::response(['value' => self::files()]),
            // A file's bytes: Graph points to a one-time address, which then sends the file.
            'content' => fn () => Http::response('', 302, ['Location' => self::DOWNLOAD . '?UniqueId=1&tempauth=one-time']),
            'download' => fn () => Http::response(self::PDF, 200, ['Content-Type' => 'application/pdf']),
        ];

        Http::fake(function (GraphRequest $request, array $options) {
            $this->limits[] = [$options['connect_timeout'] ?? null, $options['timeout'] ?? null];
            $this->sentWith[] = [$request->url(), $options];
            $url = $request->url();
            $drive = 'https://graph.microsoft.com/v1.0/drives/';

            // Each part gets the request and the options it was sent with (time limits, redirects, cURL settings).
            return match (true) {
                str_starts_with($url, 'https://login.microsoftonline.com/') => $this->microsoft['token']($request, $options),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/shares/') => $this->microsoft['root']($request, $options),
                str_starts_with($url, $drive) && str_ends_with(strtok($url, '?'), '/content') => $this->microsoft['content']($request, $options),
                str_starts_with($url, $drive) => $this->microsoft['children']($request, $options),
                str_starts_with($url, self::DOWNLOAD) => $this->microsoft['download']($request, $options),
                default => Http::response('not Microsoft', 500),
            };
        });
    }

    private function showFile(array $query, bool $json = false): TestResponse
    {
        $url = route('documents.cof-scan.file', $query);

        return $json ? $this->getJson($url) : $this->get($url);
    }

    /** The options the last request to an address containing $part was sent with. */
    private function optionsOf(string $part): array
    {
        foreach (array_reverse($this->sentWith) as [$url, $options]) {
            if (str_contains($url, $part)) {
                return $options;
            }
        }
        $this->fail("No request was sent to an address containing \"{$part}\".");
    }

    private static function file(string $name, int $size = 1000, string $modified = '2026-01-01T00:00:00Z', string $by = 'Petugas Satu'): array
    {
        return [
            'id' => strtoupper(md5($name)),
            'name' => $name,
            'size' => $size,
            'file' => ['mimeType' => 'application/pdf'],
            'lastModifiedDateTime' => $modified,
            'lastModifiedBy' => ['user' => ['displayName' => $by]],
            'webUrl' => self::FOLDER_URL . '/' . rawurlencode($name),
        ];
    }

    private static function folder(string $name): array
    {
        return ['folder' => ['childCount' => 2], 'size' => 0] + array_diff_key(self::file($name), ['file' => true]);
    }

    /** Three scans, uploaded in another order than their numbers. */
    private static function files(): array
    {
        return [
            self::file('ICTCOF2 - Budi Santoso.pdf', 22_528, '2025-03-22T01:00:00Z'),
            self::file('ICTCOF10 - Andi Wijaya.pdf', 586_000, '2026-01-03T06:22:11Z', 'Petugas Dua'),
            self::file('ICTCOF1 - Citra Dewi.pdf', 1_500_000, '2025-12-31T16:30:00Z'),
        ];
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

    private static function graphError(int $status, string $code): callable
    {
        return fn () => Http::response(['error' => ['code' => $code, 'message' => "Microsoft says {$code} (token-1, secret-1)"]], $status);
    }

    public function test_the_page_shows_the_folder_newest_first(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:15:00', 'UTC'));

        $this->get(route('documents.cof-scan.index'))->assertOk()
            ->assertSee('COF Scan')
            ->assertSee('No. COF')
            ->assertSee('Buka di SharePoint')
            ->assertSee('href="' . e(self::ADDRESS) . '"', false)
            ->assertDontSee('&amp;amp;', false);
        Http::assertNothingSent(); // opening the page itself asks Microsoft nothing

        $this->listing()->assertOk()
            ->assertJsonPath('data.0.type', 'file')
            ->assertJsonPath('data.0.name', 'ICTCOF10 - Andi Wijaya.pdf')
            ->assertJsonPath('data.0.code', 'ICTCOF10')
            ->assertJsonPath('data.0.label', 'Andi Wijaya')
            ->assertJsonPath('data.0.extension', 'pdf')
            ->assertJsonPath('data.0.size', '572 KB')
            ->assertJsonPath('data.0.modified', '03-01-2026 14:22') // 06:22 UTC in WITA
            ->assertJsonPath('data.0.modified_by', 'Petugas Dua')
            ->assertJsonPath('data.0.url', self::FOLDER_URL . '/ICTCOF10%20-%20Andi%20Wijaya.pdf?web=1')
            ->assertJsonPath('data.0.path', null)
            ->assertJsonPath('data.0.id', strtoupper(md5('ICTCOF10 - Andi Wijaya.pdf')))
            ->assertJsonPath('data.0.preview', true)
            ->assertJsonPath('data.1.code', 'ICTCOF1')
            ->assertJsonPath('data.1.modified', '01-01-2026 00:30')
            ->assertJsonPath('data.1.size', '1,4 MB')
            ->assertJsonPath('data.2.code', 'ICTCOF2')
            ->assertJsonPath('data.2.size', '22 KB')
            ->assertJsonPath('meta', ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'per_page' => 25, 'to' => 3, 'total' => 3])
            ->assertJsonPath('folder', [
                'path' => '',
                'web_url' => self::ADDRESS,
                'fetched_at' => '07-10-2026 10:15',
                'files' => 3,
                'folders' => 0,
                'size' => '2,0 MB',
                'truncated' => false,
                'stale' => false,
            ]);

        // The application signs in as itself, finds the folder from the pasted link, reads its children.
        Http::assertSent(fn (GraphRequest $request) => $request->url() === 'https://login.microsoftonline.com/tenant-1/oauth2/v2.0/token'
            && $request->method() === 'POST'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'client-1'
            && $request['client_secret'] === 'secret-1'
            && $request['scope'] === 'https://graph.microsoft.com/.default');
        $share = 'u!' . rtrim(strtr(base64_encode(self::ADDRESS), '+/', '-_'), '=');
        Http::assertSent(fn (GraphRequest $request) => $request->url() === "https://graph.microsoft.com/v1.0/shares/{$share}/driveItem"
            && $request->hasHeader('Authorization', 'Bearer token-1'));
        Http::assertSent(function (GraphRequest $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_contains($request->url(), '/v1.0/drives/b!drive-1/items/ROOT1/children?')
                && $request->hasHeader('Authorization', 'Bearer token-1')
                && ($query['$top'] ?? null) === '999'
                && str_contains($query['$select'] ?? '', 'lastModifiedDateTime');
        });
        Http::assertSentCount(3);
    }

    public function test_search_sort_and_paging_work_on_the_kept_listing(): void
    {
        $this->actingAs($this->superAdmin());
        $this->microsoft['children'] = fn () => Http::response(['value' => [
            self::file('ICTCOF2 - Budi Santoso.pdf', 3000, '2026-01-02T00:00:00Z'),
            self::file('ICTCOF10 - Andi Wijaya.pdf', 1000, '2026-01-04T00:00:00Z'),
            self::folder('2025'),
            self::file('ICTCOF1 - Citra Dewi.pdf', 2000, '2026-01-03T00:00:00Z'),
            self::file('Catatan tanpa nomor.pdf', 4000, '2026-01-01T00:00:00Z'),
            ['size' => 999_999] + self::folder('2024'), // SharePoint gives a folder the size of what is in it
        ]]);
        [$one, $two, $ten, $note] = ['ICTCOF1 - Citra Dewi.pdf', 'ICTCOF2 - Budi Santoso.pdf', 'ICTCOF10 - Andi Wijaya.pdf', 'Catatan tanpa nomor.pdf'];
        $names = fn (array $query) => $this->listing($query)->assertOk()->json('data.*.name');

        // Folders stay on top; the newest file first unless asked otherwise.
        $this->assertSame(['2024', '2025', $ten, $one, $two, $note], $names([]));
        $this->assertSame(['2024', '2025', $ten, $one, $two, $note], $names(['sort' => 'tidak ada', 'dir' => 'ke samping']));
        $this->assertSame(['2024', '2025', $note, $two, $one, $ten], $names(['sort' => 'modified', 'dir' => 'asc']));
        // Numbers in a name count as numbers: COF2 comes before COF10.
        $this->assertSame(['2024', '2025', $note, $one, $two, $ten], $names(['sort' => 'name']));
        $this->assertSame(['2025', '2024', $ten, $two, $one, $note], $names(['sort' => 'name', 'dir' => 'desc']));
        $this->assertSame(['2024', '2025', $ten, $two, $note, $one], $names(['sort' => 'label']));
        // A folder shows no size, so folders keep their name order whichever way sizes are sorted.
        $this->assertSame(['2024', '2025', $ten, $one, $two, $note], $names(['sort' => 'size']));
        $this->assertSame(['2024', '2025', $note, $two, $one, $ten], $names(['sort' => 'size', 'dir' => 'desc']));

        // Every word must be in the name, in any case.
        $this->assertSame([$two], $names(['search' => '  ictcof   BUDI ']));
        $this->assertSame([$one, $ten], $names(['search' => 'cof1', 'sort' => 'name']));
        $this->assertSame(['2025'], $names(['search' => '2025']));
        $this->listing(['search' => 'tidak ada yang begini'])->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta', ['current_page' => 1, 'from' => null, 'last_page' => 1, 'per_page' => 25, 'to' => null, 'total' => 0])
            ->assertJsonPath('folder.files', 4) // the folder itself, whatever is searched for
            ->assertJsonPath('folder.folders', 2)
            ->assertJsonPath('folder.size', '10 KB'); // the files; a folder's own size is not added on top

        $this->listing(['sort' => 'name', 'paginated' => 2, 'page' => 2])->assertOk()
            ->assertJsonPath('data.*.name', [$note, $one])
            ->assertJsonPath('meta', ['current_page' => 2, 'from' => 3, 'last_page' => 3, 'per_page' => 2, 'to' => 4, 'total' => 6]);
        // A page past the end is the last page, not an empty one.
        $this->listing(['sort' => 'name', 'paginated' => 2, 'page' => 99])->assertOk()
            ->assertJsonPath('data.*.name', [$two, $ten])
            ->assertJsonPath('meta.current_page', 3);
        $this->listing(['paginated' => 1000, 'page' => -3])->assertOk()->assertJsonPath('meta.per_page', 100)->assertJsonPath('meta.current_page', 1);
        $this->listing(['paginated' => 'banyak'])->assertOk()->assertJsonPath('meta.per_page', 25);

        $this->listing()->assertOk()
            ->assertJsonPath('data.0.type', 'folder')
            ->assertJsonPath('data.0.path', '2024')
            ->assertJsonPath('data.0.size', null)
            ->assertJsonPath('data.0.url', self::FOLDER_URL . '/2024');

        $this->assertSame(1, $this->sent('/children'), 'SharePoint is read once, the rest works on the kept copy.');
    }

    public function test_a_listing_is_kept_and_read_again_only_when_it_is_old_enough(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));

        $this->listing()->assertOk();
        $this->listing(['search' => 'budi'])->assertOk();
        $this->assertSame(1, $this->sent('/children'));

        // "Muat ulang" straight away: the copy is seconds old and is used again.
        $this->travel(5)->seconds();
        $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('folder.fetched_at', '07-10-2026 10:00');
        $this->assertSame(1, $this->sent('/children'));

        $this->travel(15)->seconds();
        $this->microsoft['children'] = fn () => Http::response(['value' => [self::file('ICTCOF99 - Baru Datang.pdf')]]);
        $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', 'ICTCOF99');
        $this->assertSame(2, $this->sent('/children'));

        // Without "Muat ulang", SharePoint is asked again after cache_minutes.
        $this->travel(9)->minutes();
        $this->listing()->assertOk();
        $this->assertSame(2, $this->sent('/children'));
        $this->travel(2)->minutes();
        $this->listing()->assertOk();
        $this->assertSame(3, $this->sent('/children'));

        // A copy dated in the future (the server clock was set back) is not taken for a fresh one.
        $this->travel(-2)->hours();
        $this->listing()->assertOk();
        $this->assertSame(4, $this->sent('/children'));

        $this->assertSame(1, $this->sent('/shares/'), 'The folder itself is looked up once.');

        // A copy kept by an earlier version of the application (its rows had no id) is not used:
        // the page reads the folder again instead of failing on the old shape.
        $key = 'sharepoint-docs:list:' . sha1(self::ADDRESS . "\n");
        $this->assertSame(2, Cache::get($key)['format'] ?? null, 'This is where listings are kept, and they carry their format.');
        Cache::put($key, ['fetched_at' => now()->getTimestamp(), 'truncated' => false, 'items' => [
            ['name' => 'ICTCOF1 - Dari Versi Lama.pdf', 'folder' => false, 'size' => 1, 'modified' => null, 'modified_by' => null, 'url' => null],
        ]], 3600);
        $this->listing()->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', 'ICTCOF99');
        $this->assertSame(5, $this->sent('/children'));
    }

    public function test_when_microsoft_cannot_be_reached_the_kept_listing_is_shown_as_old(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged) {
            if (str_starts_with($event->message, 'SharePoint')) {
                $logged[] = $event->message;
            }
        });

        $this->listing()->assertOk()->assertJsonPath('folder.stale', false);

        $this->travel(11)->minutes();
        $this->microsoft['children'] = self::graphError(503, 'serviceNotAvailable');
        $this->listing()->assertOk()
            ->assertJsonPath('folder.stale', true)
            ->assertJsonPath('folder.fetched_at', '07-10-2026 10:00')
            ->assertJsonPath('meta.total', 3);
        $this->assertSame(3, $this->sent('/children'), 'A busy Microsoft is asked a second time, once.');
        Sleep::assertSleptTimes(1);

        // For the next minute nobody waits for Microsoft again, not even with "Muat ulang".
        $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('folder.stale', true);
        $this->assertSame(3, $this->sent('/children'));
        $this->assertSame(['SharePoint could not be reached, showing the kept listing.'], $logged);

        // A dropped connection is bridged the same way ...
        $this->travel(61)->seconds();
        $this->microsoft['children'] = fn () => Http::failedConnection();
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('meta.total', 3);
        // ... and so is a failure on the way that Laravel leaves as Guzzle's own exception.
        $this->travel(61)->seconds();
        $this->microsoft['children'] = self::connectionReset();
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('meta.total', 3);
        // An answer that is not Graph's (a proxy's page with status 200) does not replace the kept list with an empty one,
        $this->travel(61)->seconds();
        $this->microsoft['children'] = fn () => Http::response('<html>Sign in to the network</html>');
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('meta.total', 3);
        // and a filter's block page with status 403 is not Microsoft withdrawing the permission: the kept list stays.
        $this->travel(61)->seconds();
        $this->microsoft['children'] = fn () => Http::response('<html>Blocked by policy</html>', 403);
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('meta.total', 3);

        $this->travel(61)->seconds();
        $this->microsoft['children'] = fn () => Http::response(['value' => [self::file('ICTCOF99 - Baru Datang.pdf')]]);
        $this->listing()->assertOk()->assertJsonPath('folder.stale', false)->assertJsonPath('meta.total', 1);

        // What is no passing trouble is not bridged: a withdrawn permission shows at once ...
        $this->travel(11)->minutes();
        $this->microsoft['children'] = self::graphError(403, 'accessDenied');
        $this->listing()->assertStatus(502)->assertJsonPath('reason', 'forbidden');
        // ... and the list that was kept does not come back at the next hiccup.
        $this->microsoft['children'] = self::graphError(503, 'serviceNotAvailable');
        $this->listing()->assertStatus(503)->assertJsonPath('reason', 'unavailable')->assertJsonMissingPath('data');
    }

    public function test_while_microsoft_cannot_be_reached_it_is_asked_once_a_minute_and_within_a_time_limit(): void
    {
        $this->actingAs($this->superAdmin());
        $this->microsoft['children'] = fn () => Http::failedConnection();

        // Nothing kept to fall back on: the failure is shown ...
        $this->listing()->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        // ... and repeated without asking again, whatever is clicked meanwhile.
        $asked = Http::recorded()->count();
        $this->listing(['refresh' => 1])->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->listing(['search' => 'budi'])->assertStatus(503);
        $this->assertSame($asked, Http::recorded()->count());

        $this->travel(61)->seconds();
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);
        $this->listing()->assertOk()->assertJsonPath('folder.stale', false)->assertJsonPath('meta.total', 3);

        // No request waits longer than sharepoint-docs.timeout, and 5 seconds for the connection.
        $this->assertNotEmpty($this->limits);
        $this->assertSame([[5, 10]], array_values(array_unique($this->limits, SORT_REGULAR)));

        // The requests of one page load share sharepoint-docs.time_limit: each gets what is left of it
        // (here every answer takes half a second, so the second gets half a second less than the first) ...
        Cache::flush();
        $this->sentWith = [];
        config(['sharepoint-docs.time_limit' => 3]);
        $this->freezeSecond();
        $prompt = $this->microsoft;
        $this->microsoft = array_map(fn (callable $answer) => $this->after(0.5, $answer), $prompt);
        $this->listing()->assertOk();
        $this->assertSame(
            ['/shares/', '/children'],
            array_map(fn (array $sent) => preg_match('#/shares/|/children#', $sent[0], $part) === 1 ? $part[0] : $sent[0], $this->sentWith),
            'The folder is looked up and read.'
        );
        $this->assertEqualsWithDelta([3.0, 2.5], array_map(fn (array $sent) => (float) $sent[1]['timeout'], $this->sentWith), 0.001);
        $this->assertEqualsWithDelta([3.0, 2.5], array_map(fn (array $sent) => (float) $sent[1]['connect_timeout'], $this->sentWith), 0.001);
        $this->microsoft = $prompt;

        // ... and with nothing left, none is started.
        Cache::flush();
        config(['sharepoint-docs.time_limit' => 0]);
        $asked = Http::recorded()->count();
        $this->listing()->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());

        // With little left, a busy Microsoft is not asked a second time.
        Cache::flush();
        config(['sharepoint-docs.time_limit' => 1.35]);
        $this->microsoft['children'] = self::graphError(503, 'serviceNotAvailable');
        $children = $this->sent('/children');
        $this->listing()->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($children + 1, $this->sent('/children'));
    }

    public function test_the_address_shown_for_the_folder_never_costs_a_request_and_is_never_a_sharing_link(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));

        // A link made with "Copy link" finds the folder, but gives access to whoever holds it: it is not shown.
        $sharing = 'https://contoso.sharepoint.com/:f:/s/Team/EaBcDeFgHiJ?e=AbC123';
        config(['sharepoint-docs.folders.cof-scan.url' => $sharing]);
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('EaBcDeFgHiJ');
        $list = $this->listing()->assertOk()->assertJsonPath('folder.web_url', self::FOLDER_URL); // SharePoint's own address of the folder
        $this->assertStringNotContainsString('EaBcDeFgHiJ', $list->getContent());
        $share = 'u!' . rtrim(strtr(base64_encode($sharing), '+/', '-_'), '=');
        Http::assertSent(fn (GraphRequest $request) => str_contains($request->url(), "/shares/{$share}/driveItem"));

        // The folder's plain address is not a view either: the page waits for the list to say where the folder is.
        config(['sharepoint-docs.folders.cof-scan.url' => self::FOLDER_URL]);
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('href="' . self::FOLDER_URL, false);
        $this->listing()->assertOk()->assertJsonPath('folder.web_url', self::FOLDER_URL);

        // A day later the folder's ids are forgotten while a listing is still kept, and Microsoft is down:
        // the kept list is shown without its address, and nothing but the one failed attempt was sent.
        $this->travel(23)->hours();
        $this->listing()->assertOk()->assertJsonPath('folder.stale', false);
        $this->travel(90)->minutes();
        $this->microsoft['root'] = fn () => Http::failedConnection();
        $shares = $this->sent('/shares/');
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('folder.web_url', null);
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('folder.web_url', null);
        $this->assertSame($shares + 1, $this->sent('/shares/'));
    }

    public function test_a_failure_is_explained_without_repeating_what_microsoft_said(): void
    {
        $this->actingAs($this->superAdmin());
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged) {
            if (str_starts_with($event->message, 'SharePoint')) {
                $logged[] = [$event->level, $event->context['reason'] ?? null];
            }
        });

        $cases = [
            // what goes wrong => [who answers, the answer, status of the page, reason given]
            'wrong secret' => ['token', fn () => Http::response(['error' => 'invalid_client', 'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: 1"], 401), 502, 'credentials'],
            // The sign-in's own 400 is about the application, not about the folder's address.
            'unknown tenant' => ['token', fn () => Http::response(['error' => 'invalid_request', 'error_description' => 'AADSTS90002: Tenant not found.'], 400), 502, 'credentials'],
            'sign-in is down' => ['token', fn () => Http::response('Bad Gateway', 502), 503, 'unavailable'],
            'sign-in is busy' => ['token', fn () => Http::response(['error' => 'temporarily_unavailable'], 429), 503, 'unavailable'],
            // A 2xx without a token is not Microsoft's answer: it says nothing about the credentials.
            'no token in the answer' => ['token', fn () => Http::response('<html>proxy</html>'), 503, 'unavailable'],
            // Neither is a web filter's own "403" a word of Microsoft on the application or the folder.
            // (The sign-in cases come first: once a token is had, the same client keeps it.)
            'a filter answers for the sign-in' => ['token', fn () => Http::response('<html>Blocked by policy</html>', 403), 503, 'unavailable'],
            // Its "400" least of all: that status from Graph would mean "no such address".
            'a filter answers 400 for the sign-in' => ['token', fn () => Http::response('<html>Blocked by policy</html>', 400), 503, 'unavailable'],
            // Being sent on to another address is no answer: nothing with the secret or the token follows.
            'the sign-in points elsewhere' => ['token', fn () => Http::response('', 307, ['Location' => 'https://login.example.com/token']), 503, 'unavailable'],
            'graph points elsewhere' => ['children', fn () => Http::response('', 302, ['Location' => 'https://graph.example.com/v1.0/children']), 503, 'unavailable'],
            'a filter answers for the address' => ['root', fn () => Http::response('<html>Blocked by policy</html>', 400), 503, 'unavailable'],
            'a filter answers for the list' => ['children', fn () => Http::response('<html>Blocked by policy</html>', 403), 503, 'unavailable'],
            'no permission' => ['children', self::graphError(403, 'accessDenied'), 502, 'forbidden'],
            'folder is gone' => ['children', self::graphError(404, 'itemNotFound'), 404, 'not_found'],
            'address is not in SharePoint' => ['root', self::graphError(400, 'invalidRequest'), 404, 'not_found'],
            'address is a file' => ['root', fn () => Http::response(['id' => 'FILE1', 'name' => 'a.pdf', 'file' => ['mimeType' => 'application/pdf'], 'parentReference' => ['driveId' => 'b!drive-1']]), 502, 'not_a_folder'],
            'ids that cannot be used' => ['root', fn () => Http::response(['id' => '../../me', 'folder' => ['childCount' => 1], 'parentReference' => ['driveId' => 'b!drive-1']]), 503, 'unavailable'],
            'graph is down' => ['children', self::graphError(500, 'generalException'), 503, 'unavailable'],
            'no connection' => ['root', fn () => Http::failedConnection(), 503, 'unavailable'],
            'token refused' => ['children', self::graphError(401, 'InvalidAuthenticationToken'), 502, 'credentials'],
            'not json' => ['children', fn () => Http::response('<html>proxy</html>', 502), 503, 'unavailable'],
            // "200 OK" from something in between must not pass for an empty folder.
            'a page instead of the list' => ['children', fn () => Http::response('<html>proxy</html>'), 503, 'unavailable'],
            'an answer without the list' => ['children', fn () => Http::response(['odd' => true]), 503, 'unavailable'],
            'connection reset' => ['children', self::connectionReset(), 503, 'unavailable'],
        ];

        $working = $this->microsoft;
        foreach ($cases as $case => [$part, $answer, $status, $reason]) {
            Cache::flush();
            $this->microsoft = [$part => $answer] + $working;

            $response = $this->listing();

            $this->assertSame([$status, $reason], [$response->getStatusCode(), $response->json('reason')], $case);
            $this->assertSame(['message', 'reason'], array_keys($response->json()), $case);
            $this->assertNotSame('', trim($response->json('message')), $case);
            foreach (['secret-1', 'token-1', 'AADSTS', 'Microsoft says', 'proxy', 'Blocked'] as $private) {
                $this->assertStringNotContainsString($private, $response->getContent(), $case);
            }
        }

        // Each of them is written down for whoever looks after the server.
        $this->assertSame(array_map(fn (array $case) => ['warning', $case[3]], array_values($cases)), $logged);
        Http::assertNotSent(fn (GraphRequest $request) => str_contains($request->url(), 'example.com'));
    }

    public function test_every_page_of_a_large_folder_is_read_and_paging_never_leaves_graph(): void
    {
        $this->actingAs($this->superAdmin());
        $next = 'https://graph.microsoft.com/v1.0/drives/b!drive-1/items/ROOT1/children?$top=999&$skiptoken=page-2';
        $this->microsoft['children'] = fn (GraphRequest $request) => str_contains($request->url(), 'skiptoken')
            ? Http::response(['value' => [self::file('ICTCOF3 - Halaman Dua.pdf')]])
            : Http::response(['value' => self::files(), '@odata.nextLink' => $next]);

        $this->listing(['sort' => 'name'])->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.*.code', ['ICTCOF1', 'ICTCOF2', 'ICTCOF3', 'ICTCOF10'])
            ->assertJsonPath('folder.truncated', false);
        $this->assertSame(2, $this->sent('/children'));
        Http::assertSent(fn (GraphRequest $request) => str_contains($request->url(), 'skiptoken=page-2') && $request->hasHeader('Authorization', 'Bearer token-1'));

        // A folder without end is cut off, and says so.
        Cache::flush();
        $this->microsoft['children'] = fn () => Http::response(['value' => [self::file('ICTCOF1 - Citra Dewi.pdf')], '@odata.nextLink' => $next]);
        $this->listing()->assertOk()->assertJsonPath('folder.truncated', true)->assertJsonPath('meta.total', 20);

        // The token is not carried to an address that only looks like Graph.
        Cache::flush();
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files(), '@odata.nextLink' => 'https://graph.microsoft.com.evil.example/v1.0/drives/x']);
        $this->listing()->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        Http::assertNotSent(fn (GraphRequest $request) => str_contains($request->url(), 'evil.example'));
    }

    public function test_a_folder_below_is_opened_by_its_path_and_other_paths_are_refused(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        // What is in SharePoint, by path below the folder.
        $tree = [
            '' => [self::folder('2025'), self::file('ICTCOF1 - Citra Dewi.pdf')],
            '2025' => [self::folder('Januari & Februari')],
            '2025/Januari & Februari' => [self::folder('Sub'), self::file('ICTCOF7 - Dalam Folder.pdf')],
        ];
        $sharePoint = function (GraphRequest $request) use (&$tree) {
            preg_match('#/items/ROOT1(?::/(.+):)?/children$#', rawurldecode(strtok($request->url(), '?')), $asked);
            $path = $asked[1] ?? '';

            return isset($tree[$path]) ? Http::response(['value' => $tree[$path]]) : self::graphError(404, 'itemNotFound')();
        };
        $this->microsoft['children'] = $sharePoint;

        // Opened straight away (a reload of the page, a kept link): the folders above are read first.
        $this->listing(['path' => '/2025/Januari & Februari/'])->assertOk()
            ->assertJsonPath('folder.path', '2025/Januari & Februari')
            ->assertJsonPath('folder.web_url', self::FOLDER_URL . '/2025/Januari%20%26%20Februari')
            ->assertJsonPath('data.0.path', '2025/Januari & Februari/Sub')
            ->assertJsonPath('data.1.code', 'ICTCOF7');
        Http::assertSent(fn (GraphRequest $request) => str_contains($request->url(), '/drives/b!drive-1/items/ROOT1:/2025/Januari%20%26%20Februari:/children?'));
        $this->assertSame(3, $this->sent('/children'), 'The folder, the one below it, and the one asked for.');
        $this->listing(['path' => '2025'])->assertOk()->assertJsonPath('data.0.path', '2025/Januari & Februari');
        $this->listing()->assertOk()->assertJsonPath('data.0.path', '2025');
        $this->assertSame(3, $this->sent('/children'));

        // A path the listings do not show gets its "not found" without a request to Microsoft ...
        $sent = Http::recorded()->count();
        foreach (['Tidak Ada', '2025/Tidak Ada', 'Tidak Ada/Sub/Lebih Dalam', '2025/januari & februari', 'ICTCOF1 - Citra Dewi.pdf'] as $path) {
            $this->listing(['path' => $path])->assertStatus(404)->assertJsonPath('reason', 'not_found')->assertJsonMissingPath('data');
        }
        // ... and one that cannot exist below the folder at all is refused as it stands.
        foreach (['../rahasia', '2025/../..', '2025/./Sub', '2025//Sub', 'a\\b', 'a:b', 'a*b', 'a?b', 'a|b', 'a"b', ' 2025 / Sub', "a\tb", str_repeat('a', 401)] as $path) {
            $this->listing(['path' => $path])->assertStatus(422)->assertJsonMissingPath('data');
        }
        $this->getJson(route('documents.cof-scan.list') . '?path[]=2025')->assertStatus(422);
        $this->assertSame($sent, Http::recorded()->count());

        $this->assertSame('', SharePointFolder::cleanPath(null));
        $this->assertSame('', SharePointFolder::cleanPath(' / '));
        $this->assertSame('2025/Jan', SharePointFolder::cleanPath('/2025/Jan/'));
        foreach (['.', '..', 'a<b', 'a>b', "a\x00b", "\xC3\x28", ['2025'], 2025] as $path) {
            $this->assertNull(SharePointFolder::cleanPath($path));
        }

        // A folder removed in SharePoint after it was listed: Microsoft's "not found" is passed on,
        // and the configured folder is not looked up again because of it.
        $removed = $tree['2025/Januari & Februari'];
        unset($tree['2025/Januari & Februari']);
        $this->travel(11)->minutes();
        $this->listing(['path' => '2025/Januari & Februari'])->assertStatus(404)->assertJsonPath('reason', 'not_found');
        $tree['2025/Januari & Februari'] = $removed;
        $this->listing(['path' => '2025/Januari & Februari'])->assertOk()->assertJsonPath('data.1.code', 'ICTCOF7');
        $this->assertSame(1, $this->sent('/shares/'));

        // "Not found" for the configured folder itself is different: another folder may have taken
        // its place at that address, so the address is looked up afresh.
        $this->travel(11)->minutes();
        $this->microsoft['children'] = self::graphError(404, 'itemNotFound');
        $this->listing()->assertStatus(404)->assertJsonPath('reason', 'not_found');
        $this->microsoft['children'] = $sharePoint;
        $this->listing()->assertOk()->assertJsonPath('data.0.path', '2025');
        $this->assertSame(2, $this->sent('/shares/'));
    }

    public function test_trouble_below_stays_below_and_a_refusal_above_takes_what_is_below_with_it(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $tree = [
            '' => [self::folder('2025'), self::folder('Rusak'), self::folder('Aneh'), self::file('ICTCOF1 - Citra Dewi.pdf')],
            '2025' => [self::file('ICTCOF7 - Dalam Folder.pdf')],
        ];
        // Paths for which the pretend Graph answers with an error instead of a list.
        $broken = ['Rusak' => self::graphError(500, 'generalException'), 'Aneh' => self::graphError(400, 'invalidRequest')];
        $this->microsoft['children'] = function (GraphRequest $request) use (&$tree, &$broken) {
            preg_match('#/items/ROOT1(?::/(.+):)?/children$#', rawurldecode(strtok($request->url(), '?')), $asked);
            $path = $asked[1] ?? '';

            return match (true) {
                isset($broken[$path]) => $broken[$path]($request),
                isset($tree[$path]) => Http::response(['value' => $tree[$path]]),
                default => self::graphError(404, 'itemNotFound')($request),
            };
        };

        // A folder below that Graph fails to read is not asked for again for a minute ...
        $this->listing(['path' => 'Rusak'])->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $asked = Http::recorded()->count();
        $this->listing(['path' => 'Rusak'])->assertStatus(503);
        $this->assertSame($asked, Http::recorded()->count());
        // ... but its trouble is its own: the folder and the other folders below are read as usual.
        $this->travel(20)->seconds();
        $read = $this->sent('/children');
        $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('folder.stale', false);
        $this->assertSame($read + 1, $this->sent('/children'), 'The folder was read again.');
        $this->listing(['path' => '2025'])->assertOk()->assertJsonPath('data.0.code', 'ICTCOF7');
        // A name Graph cannot address at all is "not found", not an outage.
        $this->listing(['path' => 'Aneh'])->assertStatus(404)->assertJsonPath('reason', 'not_found');
        $this->listing(['path' => '2025'])->assertOk();

        // Microsoft goes away while a folder below is open: it is tried for the folder above,
        // and not once more for every level below it.
        $this->travel(11)->minutes();
        $broken[''] = self::graphError(503, 'serviceNotAvailable');
        $children = $this->sent('/children');
        $this->listing(['path' => '2025'])->assertOk()->assertJsonPath('folder.stale', true)->assertJsonPath('data.0.code', 'ICTCOF7');
        $this->assertSame($children + 2, $this->sent('/children'), 'The folder above was tried (twice, being busy); the one below was not.');

        // A refusal for the folder takes the kept lists below it with it: "2025" is still in the
        // cache, but it is not shown when Microsoft cannot be reached next.
        $this->travel(61)->seconds();
        $broken[''] = self::graphError(403, 'accessDenied');
        $this->listing()->assertStatus(502)->assertJsonPath('reason', 'forbidden');
        $broken[''] = self::graphError(503, 'serviceNotAvailable');
        $this->listing()->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->listing(['path' => '2025'])->assertStatus(503)->assertJsonMissingPath('data');
    }

    public function test_a_database_that_gets_the_radio_roles_after_the_menu_still_gives_it_to_admin_radio(): void
    {
        // A base-app database is upgraded with "migrate" first (the menu arrives, for Super Admin
        // only) and RadioMenuSeeder after (the radio roles arrive).
        $radioRoles = DB::table('roles')->whereIn('name', ['Admin Radio', 'Viewer Radio'])->pluck('id');
        DB::table('menu_role_permission')->whereIn('role_id', $radioRoles)->delete();
        DB::table('menu_role')->whereIn('role_id', $radioRoles)->delete();
        DB::table('roles')->whereIn('id', $radioRoles)->delete();
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $migration->up();

        $menuId = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->value('id');
        $read = CheckMenuPermission::PERMISSION_IDS['Read'];
        $grants = fn () => [
            DB::table('menu_role')->where('menu_id', $menuId)->orderBy('role_id')->pluck('role_id')->map(fn ($id) => (int) $id)->all(),
            DB::table('menu_role_permission')->where('menu_id', $menuId)->orderBy('role_id')->get()
                ->map(fn ($grant) => [(int) $grant->role_id, (int) $grant->permission_id])->all(),
        ];
        $this->assertSame([[1], [[1, $read]]], $grants());

        $this->seed(RadioMenuSeeder::class);

        $admin = (int) DB::table('roles')->where('name', 'Admin Radio')->value('id');
        $this->assertGreaterThan(1, $admin);
        $this->assertSame([[1, $admin], [[1, $read], [$admin, $read]]], $grants()); // read only, and not Viewer Radio
        $this->actingAs($this->makeUser('admin.radio@test.local', 'Admin Radio'))->getJson(route('documents.cof-scan.list'))->assertOk();

        // That default is applied once, when the role is made. Taken away by the owner, it stays away.
        DB::table('menu_role')->where(['menu_id' => $menuId, 'role_id' => $admin])->delete();
        DB::table('menu_role_permission')->where(['menu_id' => $menuId, 'role_id' => $admin])->delete();
        $this->seed(RadioMenuSeeder::class);
        $this->assertSame([[1], [[1, $read]]], $grants());
    }

    public function test_names_outside_the_pattern_and_odd_items_are_listed_as_they_are(): void
    {
        $this->actingAs($this->superAdmin());
        $office = ['webUrl' => 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D&file=a.xlsx'] + self::file('ICTCOF8 - Daftar Aset.xlsx');
        $plain = ['webUrl' => 'http://contoso.sharepoint.com/a.pdf'] + self::file('ICTCOF9 - Tautan Biasa.pdf');
        $script = ['webUrl' => 'javascript:alert(1)'] + self::file('ICTCOF6 - Tautan Aneh.pdf');
        $byApplication = ['lastModifiedDateTime' => 'bukan tanggal', 'lastModifiedBy' => ['application' => ['displayName' => 'Pemindai']]]
            + self::file('ICTCOF5 - Unggahan Otomatis.pdf');
        $markup = '<img src=x onerror=alert(1)> - <b>x</b>.pdf';
        $this->microsoft['children'] = fn () => Http::response(['value' => [
            self::file('Berita Acara 2025.pdf'),
            self::file('ICTCOF7 - Budi - revisi.pdf'),
            self::file('ICTCOF3 - .pdf'),
            self::file('- Tanpa Nomor.pdf'),
            self::file('README'),
            self::file($markup),
            $office,
            $plain,
            $script,
            $byApplication,
            ['name' => 'ICTCOF4 - Tanpa Keterangan.PDF'], // nothing but a name
            ['size' => 5], // no name: not listed
            'not an item',
        ]]);

        $rows = collect($this->listing(['paginated' => 100])->assertOk()->json('data'))->keyBy('name');
        $this->assertCount(11, $rows);
        $parts = fn (string $name) => [$rows[$name]['code'], $rows[$name]['label'], $rows[$name]['extension']];

        $this->assertSame(['', 'Berita Acara 2025', 'pdf'], $parts('Berita Acara 2025.pdf'));
        $this->assertSame(['ICTCOF7', 'Budi - revisi', 'pdf'], $parts('ICTCOF7 - Budi - revisi.pdf'));
        $this->assertSame(['ICTCOF3', '', 'pdf'], $parts('ICTCOF3 - .pdf'));
        $this->assertSame(['', '- Tanpa Nomor', 'pdf'], $parts('- Tanpa Nomor.pdf'));
        $this->assertSame(['', 'README', ''], $parts('README'));
        // Markup in a name is data: it comes back as it is, the page escapes it.
        $this->assertSame(['<img src=x onerror=alert(1)>', '<b>x</b>', 'pdf'], $parts($markup));

        $this->assertSame('https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D&file=a.xlsx&web=1', $rows['ICTCOF8 - Daftar Aset.xlsx']['url']);
        $this->assertSame('xlsx', $rows['ICTCOF8 - Daftar Aset.xlsx']['extension']);
        // Only ordinary https links are passed on.
        $this->assertNull($rows['ICTCOF9 - Tautan Biasa.pdf']['url']);
        $this->assertNull($rows['ICTCOF6 - Tautan Aneh.pdf']['url']);
        $this->assertSame([null, 'Pemindai'], [$rows['ICTCOF5 - Unggahan Otomatis.pdf']['modified'], $rows['ICTCOF5 - Unggahan Otomatis.pdf']['modified_by']]);
        $this->assertSame(
            ['type' => 'file', 'id' => null, 'name' => 'ICTCOF4 - Tanpa Keterangan.PDF', 'code' => 'ICTCOF4', 'label' => 'Tanpa Keterangan', 'extension' => 'pdf',
                'size' => '0 B', 'modified' => null, 'modified_by' => null, 'url' => null, 'path' => null, 'preview' => false],
            $rows['ICTCOF4 - Tanpa Keterangan.PDF']
        );

        // A folder configured without a name pattern has one name column.
        config(['sharepoint-docs.folders.cof-scan.name_split' => null]);
        $this->get(route('documents.cof-scan.index'))->assertOk()->assertDontSee('No. COF');
        $this->listing(['search' => 'ICTCOF7'])->assertOk()
            ->assertJsonPath('data.0.code', '')
            ->assertJsonPath('data.0.label', 'ICTCOF7 - Budi - revisi');
    }

    public function test_a_listed_pdf_is_shown_inside_the_application(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged) {
            if (str_starts_with($event->message, 'SharePoint file')) {
                $logged[] = [$event->level, $event->message, $event->context['user'] ?? null, $event->context['file'] ?? null];
            }
        });

        $row = $this->listing()->assertOk()->json('data.0');
        $this->assertSame(['ICTCOF10 - Andi Wijaya.pdf', true], [$row['name'], $row['preview']]);

        $response = $this->showFile(['id' => $row['id']]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Length', (string) strlen(self::PDF))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::PDF, $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('inline; filename="ICTCOF10 - Andi Wijaya.pdf"', $response->headers->get('Content-Disposition'));

        // The token goes to Graph only: the address Graph points to is asked without it.
        Http::assertSent(fn (GraphRequest $request) => $request->url() === "https://graph.microsoft.com/v1.0/drives/b!drive-1/items/{$row['id']}/content"
            && $request->hasHeader('Authorization', 'Bearer token-1'));
        Http::assertSent(fn (GraphRequest $request) => $request->url() === self::DOWNLOAD . '?UniqueId=1&tempauth=one-time'
            && ! $request->hasHeader('Authorization'));
        // The application reads the file for the reader: who looked at what is written down.
        $this->assertSame([['info', 'SharePoint file shown in the application.', $user->id, 'ICTCOF10 - Andi Wijaya.pdf']], $logged);

        // No request that carries the token is sent on to another address by the HTTP client, Graph's
        // pointer to the file least of all; the address it gives is followed, over https only, and
        // its transfer is watched.
        $pointer = $this->optionsOf('/content');
        $download = $this->optionsOf(self::DOWNLOAD);
        foreach (['login.microsoftonline.com', '/shares/', '/children', '/content'] as $part) {
            $this->assertFalse($this->optionsOf($part)['allow_redirects'], $part);
        }
        $this->assertSame([2, ['https']], [$download['allow_redirects']['max'], $download['allow_redirects']['protocols']]);
        $this->assertIsCallable($download['curl'][CURLOPT_XFERINFOFUNCTION]);
        $this->assertFalse($download['curl'][CURLOPT_NOPROGRESS]);
        // The file itself may take what is left of preview_time_limit (25 s), not just the 10 s of one request.
        $this->assertSame([5, 10], [$pointer['connect_timeout'], $pointer['timeout']]);
        $this->assertTrue($download['connect_timeout'] == 5 && $download['timeout'] > 20 && $download['timeout'] < 25, "download: {$download['timeout']} s");

        // Showing a file has one time limit for everything it needs, a re-read of the list
        // included: each request gets what is left of it by then (here every answer takes half a second).
        Cache::flush();
        $this->sentWith = [];
        config(['sharepoint-docs.preview_time_limit' => 3]);
        $this->freezeSecond();
        $prompt = $this->microsoft;
        $this->microsoft = array_map(fn (callable $answer) => $this->after(0.5, $answer), $prompt);
        $this->showFile(['id' => $row['id']])->assertOk();
        $this->assertSame(
            ['/shares/', '/children', '/content', '/download.aspx'],
            array_map(fn (array $sent) => preg_match('#/shares/|/children|/content|/download\.aspx#', $sent[0], $part) === 1 ? $part[0] : $sent[0], $this->sentWith),
            'The folder is looked up and listed, then the file is located and fetched.'
        );
        $this->assertEqualsWithDelta([3.0, 2.5, 2.0, 1.5], array_map(fn (array $sent) => (float) $sent[1]['timeout'], $this->sentWith), 0.001);
        $this->microsoft = $prompt;
        config(['sharepoint-docs.preview_time_limit' => 25]);

        // Graph may also send the bytes itself, without pointing elsewhere.
        $this->microsoft['content'] = fn () => Http::response(self::PDF);
        $this->showFile(['id' => $row['id']])->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // A name that is not plain ASCII still makes a valid header.
        Cache::flush();
        $odd = self::file('ICTCOF11 - Nur’aini 100% “asli”.pdf');
        $this->microsoft['children'] = fn () => Http::response(['value' => [$odd]]);
        $this->listing()->assertOk();
        $disposition = $this->showFile(['id' => $odd['id']])->assertOk()->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline; filename="ICTCOF11 - Nur_aini 100_ _asli_.pdf"; filename*=utf-8\'\'ICTCOF11%20-%20Nur', $disposition);
    }

    public function test_only_listed_files_of_a_safe_kind_and_size_are_shown(): void
    {
        $this->actingAs($this->superAdmin());
        config(['sharepoint-docs.preview_max_mb' => 1]);
        $files = [
            'pdf' => self::file('ICTCOF1 - Citra Dewi.pdf'),
            'png' => self::file('ICTCOF2 - Foto Serah Terima.PNG'),
            'xlsx' => self::file('ICTCOF3 - Daftar Aset.xlsx'),
            'html' => self::file('ICTCOF4 - Halaman.html'),
            'svg' => self::file('ICTCOF5 - Gambar.svg'),
            'disguised' => self::file('ICTCOF6 - Menyamar.pdf'),
            'large' => self::file('ICTCOF7 - Terlalu Besar.pdf', 1024 * 1024 + 1),
            'at the limit' => self::file('ICTCOF8 - Pas Batas.pdf', 1024 * 1024),
            'folder' => self::folder('2025'),
        ];
        $this->microsoft['children'] = fn () => Http::response(['value' => [...array_values($files), ['name' => 'ICTCOF9 - Tanpa Id.pdf', 'size' => 10]]]);

        // The list says which rows get the eye: the same rules the file route applies.
        $rows = collect($this->listing(['paginated' => 100])->assertOk()->json('data'))->keyBy('name');
        $eye = fn (string $which) => $rows[$files[$which]['name']]['preview'];
        $this->assertSame(
            ['pdf' => true, 'png' => true, 'xlsx' => false, 'html' => false, 'svg' => false, 'disguised' => true, 'large' => false, 'at the limit' => true, 'folder' => false],
            collect($files)->map(fn ($file, $which) => $eye($which))->all()
        );
        $this->assertFalse($rows['ICTCOF9 - Tanpa Id.pdf']['preview']);
        // The "Tipe" column sorts by kind (folders first, as always).
        $this->assertSame(
            ['', 'html', 'pdf', 'pdf', 'pdf', 'pdf', 'pdf', 'png', 'svg', 'xlsx'],
            $this->listing(['sort' => 'extension', 'paginated' => 100])->assertOk()->json('data.*.extension')
        );
        $show = fn (string $which, bool $json = true) => $this->showFile(['id' => $files[$which]['id']], $json);

        // Never asked for at all: other kinds (a page or an SVG would run as part of this application),
        // files over the limit, folders, ids the list does not show, and what is no id.
        foreach (['xlsx', 'html', 'svg'] as $which) {
            $show($which)->assertStatus(415)->assertJsonPath('reason', 'unsupported');
        }
        $show('large')->assertStatus(413)->assertJsonPath('reason', 'too_large');
        $show('folder')->assertStatus(404)->assertJsonPath('reason', 'not_found');
        $this->showFile(['id' => strtoupper(md5('tidak ada di daftar'))], true)->assertStatus(404)->assertJsonPath('reason', 'not_found');
        foreach ([[], ['id' => ''], ['id' => ['x']], ['id' => $files['pdf']['id'], 'path' => '../..']] as $query) {
            $this->showFile($query, true)->assertStatus(422)->assertExactJson(['message' => 'File tidak dikenali.']);
        }
        $this->showFile(['id' => '../../../me/drive/root'], true)->assertStatus(404);
        $this->assertSame(0, $this->sent('/content'));
        $this->assertSame(0, $this->sent(self::DOWNLOAD));

        // Shown: a PDF, an image, and a file exactly at the limit.
        $show('pdf', false)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->microsoft['download'] = fn () => Http::response("\x89PNG\r\n\x1a\n" . str_repeat("\0", 32));
        $show('png', false)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->microsoft['download'] = fn () => Http::response(str_pad(self::PDF, 1024 * 1024, ' '));
        $show('at the limit', false)->assertOk();

        // A page that only carries the name of a PDF is not passed on.
        $this->microsoft['download'] = fn () => Http::response('<html><script>alert(document.cookie)</script></html>', 200, ['Content-Type' => 'application/pdf']);
        $refused = $show('disguised', false)->assertStatus(415)->assertSee('tidak bisa ditampilkan di sini');
        $this->assertStringNotContainsString('alert(', $refused->getContent());
        // Neither is one that turned out larger than the list said,
        $this->microsoft['download'] = fn () => Http::response(str_pad(self::PDF, 1024 * 1024 + 1, ' '));
        $show('pdf')->assertStatus(413)->assertJsonPath('reason', 'too_large');
        // also when Graph sends the bytes itself instead of pointing to them.
        $this->microsoft['content'] = fn () => Http::response(str_pad(self::PDF, 1024 * 1024 + 1, ' '));
        $show('at the limit')->assertStatus(413)->assertJsonPath('reason', 'too_large');
        $this->microsoft['content'] = fn () => Http::response('', 302, ['Location' => self::DOWNLOAD . '?tempauth=one-time']);

        // Every kind of image that is shown, by the first bytes such a file has; other bytes under that name are not.
        $kinds = [
            'jpg' => ['image/jpeg', "\xFF\xD8\xFF\xE0\x00\x10JFIF"],
            'jpeg' => ['image/jpeg', "\xFF\xD8\xFF\xE1\x00\x18Exif"],
            'gif' => ['image/gif', 'GIF89a' . "\x01\x00\x01\x00"],
            'webp' => ['image/webp', "RIFF\x24\x00\x00\x00WEBPVP8 "],
            'png' => ['image/png', "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR"],
        ];
        Cache::flush();
        $images = array_map(fn (string $kind) => self::file("ICTCOF20 - Foto.{$kind}"), array_combine(array_keys($kinds), array_keys($kinds)));
        $this->microsoft['children'] = fn () => Http::response(['value' => array_values($images)]);
        $this->assertSame(array_fill(0, count($kinds), true), $this->listing()->assertOk()->json('data.*.preview'));
        foreach ($kinds as $kind => [$type, $bytes]) {
            $this->microsoft['download'] = fn () => Http::response($bytes);
            $shown = $this->showFile(['id' => $images[$kind]['id']])->assertOk()->assertHeader('Content-Type', $type);
            $this->assertSame($bytes, $shown->getContent(), $kind);
            $this->microsoft['download'] = fn () => Http::response(self::PDF);
            $this->showFile(['id' => $images[$kind]['id']], true)->assertStatus(415)->assertJsonPath('reason', 'unsupported');
        }
    }

    public function test_a_transfer_is_given_up_when_it_is_too_big_and_named_when_it_is_too_slow(): void
    {
        $this->actingAs($this->superAdmin());
        config(['sharepoint-docs.preview_max_mb' => 1]);
        $limit = 1024 * 1024;
        $this->microsoft['children'] = fn () => Http::response(['value' => [
            self::file('ICTCOF1 - Satu.pdf'), self::file('ICTCOF2 - Dua.pdf'), self::file('ICTCOF3 - Tiga.pdf'),
        ]]);
        [$first, $second, $third] = $this->listing(['sort' => 'name'])->assertOk()->json('data.*.id');
        $pdf = fn () => Http::response(self::PDF);
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged) {
            if ($event->message === 'SharePoint file could not be shown.') {
                $logged[] = $event->context['detail'];
            }
        });

        // More announced than the limit, or more arriving than was announced: stopped at once, "too large".
        $this->microsoft['download'] = self::transfer($limit + 1, 0, $pdf);
        $this->showFile(['id' => $first], true)->assertStatus(413)->assertJsonPath('reason', 'too_large');
        $this->microsoft['download'] = self::transfer(0, $limit + 1, $pdf);
        $this->showFile(['id' => $first], true)->assertStatus(413)->assertJsonPath('reason', 'too_large');
        // Up to the limit the transfer goes on.
        $this->microsoft['download'] = self::transfer($limit, $limit, $pdf);
        $this->showFile(['id' => $first])->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Bytes of the file were arriving when the time ran out: a slow line, said as that and not as an outage ...
        $logged = [];
        $this->microsoft['download'] = self::transfer(500_000, 100_000, self::timeout());
        $this->showFile(['id' => $second], true)->assertStatus(504)->assertExactJson([
            'message' => 'File ini terlalu lama diambil dari SharePoint (koneksi lambat atau file besar). Buka di SharePoint.', 'reason' => 'too_slow',
        ]);
        // (what went wrong is written down, without the part of the address that is the permission to fetch the file)
        $this->assertCount(1, $logged);
        $this->assertStringContainsString('cURL error 28: Operation timed out', $logged[0]);
        $this->assertStringContainsString('/download.aspx?', $logged[0]);
        $this->assertStringNotContainsString('tempauth', $logged[0]);
        $this->assertStringNotContainsString('one-time', $logged[0]);
        // ... and not tried again for a minute, repeated as what it was.
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $second], true)->assertStatus(504)->assertJsonPath('reason', 'too_slow');
        $this->assertSame($asked, Http::recorded()->count());
        // Nothing had arrived when the time ran out: that is "cannot be reached".
        $this->microsoft['download'] = self::transfer(0, 0, self::timeout());
        $this->showFile(['id' => $third], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        // Neither holds back the other files or the list.
        $this->microsoft['download'] = $pdf;
        $this->showFile(['id' => $first])->assertOk();
        $this->travel(20)->seconds();
        $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('folder.stale', false);

        // "Too slow" is said only when it was the time that ran out, and only of the file's own bytes.
        $attempt = 0;
        $sentOn = [];
        $elsewhere = self::DOWNLOAD . '?UniqueId=2&tempauth=one-time';
        $cases = [
            // what happens at the download address => [the address answering, status of the page, reason given]
            'in time, if slowly' => [$this->after(20, self::transfer(500_000, 500_000, $pdf)), 200, null],
            'the time is over while the file is arriving' => [$this->after(26, self::transfer(500_000, 100_000, $pdf)), 504, 'too_slow'],
            'the time is over and nothing has arrived' => [$this->after(26, self::transfer(0, 0, $pdf)), 503, 'unavailable'],
            'the connection is cut while the file is arriving' => [self::transfer(500_000, 300_000, self::connectionReset()), 503, 'unavailable'],
            'the time runs out on an error page' => [self::transfer(500, 100, self::timeout(), 500), 503, 'unavailable'],
            'the time runs out after the page of a redirect' => [self::transfer(500, 100, self::timeout(), 302), 503, 'unavailable'],
            // A busy address is asked a second time: what it sent the first time is not the file.
            'busy, then silent' => [function (GraphRequest $request, array $options) use (&$attempt) {
                return ++$attempt === 1
                    ? self::transfer(4, 4, fn () => Http::response('Busy', 503), 503)($request, $options)
                    : self::transfer(0, 0, self::timeout(), 0)($request, $options);
            }, 503, 'unavailable'],
            // Should the address send the application on, it follows over https only ...
            'sent on to plain http' => [
                self::transfer(12, 12, fn () => Http::response('Object moved', 302, ['Location' => 'http://contoso.sharepoint.com/elsewhere']), 302), 503, 'unavailable',
            ],
            // ... and the time allowed is for all of it, not for each address anew.
            'sent on, and the time is over there' => [function (GraphRequest $request, array $options) use ($elsewhere, $pdf, &$sentOn) {
                if ($request->url() !== $elsewhere) {
                    return $this->after(15, self::transfer(0, 0, fn () => Http::response('', 302, ['Location' => $elsewhere]), 302))($request, $options);
                }
                $sentOn[] = $request->hasHeader('Authorization') ? 'with the token' : 'without the token';

                return $this->after(15, self::transfer(500_000, 100_000, $pdf))($request, $options);
            }, 504, 'too_slow'],
        ];
        $this->freezeSecond();
        foreach ($cases as $case => [$download, $status, $reason]) {
            Cache::flush();
            $this->microsoft['download'] = $pdf;
            $id = $this->listing()->assertOk()->json('data.0.id');
            $this->microsoft['download'] = $download;

            $response = $this->showFile(['id' => $id], true);

            $this->assertSame([$status, $reason], [$response->getStatusCode(), $status === 200 ? null : $response->json('reason')], $case);
        }
        $this->assertSame(2, $attempt, 'The busy address is asked a second time.');
        $this->assertSame(['without the token'], $sentOn);
        Http::assertNotSent(fn (GraphRequest $request) => str_starts_with($request->url(), 'http://'));
    }

    public function test_a_file_in_a_folder_below_is_shown_by_its_folders_path(): void
    {
        $this->actingAs($this->superAdmin());
        $inside = self::file('ICTCOF7 - Dalam Folder.pdf');
        $this->microsoft['children'] = fn (GraphRequest $request) => str_contains($request->url(), ':/children')
            ? Http::response(['value' => [$inside]])
            : Http::response(['value' => [self::folder('2025'), self::file('ICTCOF1 - Citra Dewi.pdf')]]);

        $this->showFile(['id' => $inside['id'], 'path' => '2025'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Http::assertSent(fn (GraphRequest $request) => str_ends_with($request->url(), "/drives/b!drive-1/items/{$inside['id']}/content"));

        // In the folder itself there is no such file, and a made-up folder has none either.
        $this->showFile(['id' => $inside['id']], true)->assertStatus(404);
        $this->showFile(['id' => $inside['id'], 'path' => 'Tidak Ada'], true)->assertStatus(404);
        $this->assertSame(1, $this->sent('/content'));
    }

    public function test_a_file_that_cannot_be_fetched_is_explained_in_the_viewer(): void
    {
        $this->actingAs($this->superAdmin());
        $this->travelTo(Carbon::parse('2026-10-07 02:00:00', 'UTC'));
        $id = $this->listing()->assertOk()->json('data.0.id');

        // Removed in SharePoint since it was listed: said so, and the list is read again next time.
        $this->microsoft['content'] = self::graphError(404, 'itemNotFound');
        $this->showFile(['id' => $id])->assertStatus(404)
            ->assertSee('File tidak ditemukan di SharePoint')
            ->assertSee('<p id="fileMessage">', false)
            ->assertDontSee('Microsoft says');
        $this->showFile(['id' => $id], true)->assertStatus(404)->assertExactJson([
            'message' => 'File tidak ditemukan di SharePoint. Mungkin sudah dipindahkan atau dihapus; muat ulang daftarnya.', 'reason' => 'not_found',
        ]);
        $this->assertSame(2, $this->sent('/children'), 'The list is read again after a file turned out to be gone.');

        // The one-time address failing is a passing trouble, whatever status it gives ...
        $this->microsoft['content'] = fn () => Http::response('', 302, ['Location' => self::DOWNLOAD . '?tempauth=expired']);
        $this->microsoft['download'] = fn () => Http::response('Access denied', 403);
        $this->showFile(['id' => $id], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        // ... after which that file is not asked for again for a minute,
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $id], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());
        // while one file's trouble holds back neither the list nor the other files.
        $this->travel(20)->seconds();
        $other = $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('folder.stale', false)->json('data.1.id');
        $this->microsoft['download'] = fn () => Http::response(self::PDF);
        $this->showFile(['id' => $other])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $id], true)->assertStatus(503);
        $this->assertSame($asked, Http::recorded()->count());

        // When Microsoft cannot be reached for the folder at all, no file is asked for either.
        $this->travel(11)->minutes();
        $this->microsoft['children'] = fn () => Http::failedConnection();
        $this->listing()->assertOk()->assertJsonPath('folder.stale', true);
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $other], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());
        $this->microsoft['children'] = fn () => Http::response(['value' => self::files()]);

        $failures = [
            // what goes wrong => [who answers, the answer, status, reason]
            'graph refuses the application' => ['content', self::graphError(403, 'accessDenied'), 502, 'forbidden'],
            'graph is busy' => ['content', self::graphError(503, 'serviceNotAvailable'), 503, 'unavailable'],
            'a filter answers instead of graph' => ['content', fn () => Http::response('<html>Blocked by policy</html>', 403), 503, 'unavailable'],
            'graph points nowhere' => ['content', fn () => Http::response('', 302), 503, 'unavailable'],
            'graph points to plain http' => ['content', fn () => Http::response('', 302, ['Location' => 'http://contoso.sharepoint.com/download.aspx?tempauth=1']), 503, 'unavailable'],
            'the address is gone' => ['download', fn () => Http::response('Not found', 404), 404, 'not_found'],
            'the address is busy' => ['download', fn () => Http::response('Busy', 503), 503, 'unavailable'],
            'the connection drops' => ['download', self::connectionReset(), 503, 'unavailable'],
        ];
        $working = [
            'content' => fn () => Http::response('', 302, ['Location' => self::DOWNLOAD . '?tempauth=one-time']),
            'download' => fn () => Http::response(self::PDF),
        ] + $this->microsoft;
        foreach ($failures as $case => [$part, $answer, $status, $reason]) {
            Cache::flush();
            $this->microsoft = $working;
            $id = $this->listing()->assertOk()->json('data.0.id');
            $this->microsoft = [$part => $answer] + $working;

            $response = $this->showFile(['id' => $id], true);

            $this->assertSame([$status, $reason], [$response->getStatusCode(), $response->json('reason')], $case);
            foreach (['secret-1', 'token-1', 'Microsoft says', 'tempauth'] as $private) {
                $this->assertStringNotContainsString($private, $response->getContent(), $case);
            }
        }
        Http::assertNotSent(fn (GraphRequest $request) => str_starts_with($request->url(), 'http://'));

        // Microsoft going away while the list is still fresh: the first file finds out, and the
        // others are not tried one by one (each would wait for its own timeout).
        Cache::flush();
        $this->microsoft = $working;
        [$one, $two] = $this->listing()->assertOk()->json('data.*.id');
        $this->microsoft['content'] = fn () => Http::failedConnection();
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $two], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());

        // Graph's own error for one item is that item's trouble: the next file is still fetched.
        Cache::flush();
        $this->microsoft = $working;
        [$one, $two] = $this->listing()->assertOk()->json('data.*.id');
        $this->microsoft['content'] = self::graphError(500, 'generalException');
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->microsoft['content'] = $working['content'];
        $this->showFile(['id' => $two])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $one], true)->assertStatus(503);
        $this->assertSame($asked, Http::recorded()->count());

        // Likewise the one-time address failing in transport (a reset): that file's trouble, not the folder's.
        Cache::flush();
        $this->microsoft = $working;
        [$one, $two] = $this->listing()->assertOk()->json('data.*.id');
        $this->microsoft['download'] = self::connectionReset();
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->microsoft['download'] = $working['download'];
        $this->showFile(['id' => $two])->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Something in between answering for Graph with a page and "200 OK" (here when the folder's
        // ids have to be looked up again under a list that is still fresh) is no word of Graph on
        // that file: the others are not tried one by one either.
        Cache::flush();
        $this->microsoft = $working;
        $this->listing()->assertOk();
        $this->travel(23 * 60 + 55)->minutes();
        [$one, $two] = $this->listing()->assertOk()->assertJsonPath('folder.stale', false)->json('data.*.id');
        $this->travel(6)->minutes();
        $this->microsoft['root'] = fn () => Http::response('<html>Sign in to the network</html>');
        $shares = $this->sent('/shares/');
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($shares + 1, $this->sent('/shares/'), 'The folder is looked up again after a day.');
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $two], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());
    }

    public function test_time_used_up_before_graph_had_its_say_holds_back_only_that_file(): void
    {
        $this->actingAs($this->superAdmin());
        $this->freezeSecond();
        $prompt = $this->microsoft;
        $ids = function (): array {
            Cache::flush();
            $ids = $this->listing()->assertOk()->json('data.*.id');
            $this->travel(11)->minutes(); // the list has to be read again before a file is shown

            return $ids;
        };

        // Microsoft answering the request for the list slowly: busy at first, then with the list.
        $slowList = function (float $busyAfter, float $listAfter) use ($prompt): callable {
            $asked = 0;

            return function (GraphRequest $request, array $options) use (&$asked, $busyAfter, $listAfter, $prompt) {
                return ++$asked === 1
                    ? $this->after($busyAfter, self::graphError(503, 'serviceNotAvailable'))($request, $options)
                    : $this->after($listAfter, $prompt['children'])($request, $options);
            };
        };

        // Every request is answered in its own time, but together they leave less than a second
        // for the file (sign-in 8 s, the list 9.5 s and 7 s of the 25): it is not asked for, and
        // not again at once ...
        [$one, $two] = $ids();
        $this->microsoft = ['token' => $this->after(8, $prompt['token']), 'children' => $slowList(9.5, 7)] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame(3, $this->sent('/children'), 'The list was read again, at the second request.');
        $this->assertSame(0, $this->sent('/content'));
        $this->microsoft = $prompt;
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());
        // ... but Microsoft did answer: the other files and the list are not held back.
        $this->showFile(['id' => $two])->assertOk();
        $this->travel(20)->seconds();
        $this->listing(['refresh' => 1])->assertOk()->assertJsonPath('folder.stale', false);

        // The same when the file was still asked for, with the 6 seconds that were left instead of
        // the 10 a request gets, and they ran out.
        [$one, $two] = $ids();
        $this->microsoft = ['children' => $slowList(9.5, 9.5), 'content' => self::timeout()] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertEqualsWithDelta(6.0, $this->optionsOf('/content')['timeout'], 0.001);
        $this->microsoft = $prompt;
        $this->showFile(['id' => $two])->assertOk();

        // A request that had its 10 seconds and got no answer is Microsoft not answering: nothing is asked for a while.
        [$one, $two] = $ids();
        $this->microsoft = ['content' => self::timeout()] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame(10, $this->optionsOf('/content')['timeout']);
        $this->microsoft = $prompt;
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $two], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());

        // So is one that had less time but failed for another reason than the time.
        [$one, $two] = $ids();
        $this->microsoft = ['children' => $slowList(9.5, 9.5), 'content' => self::connectionReset()] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertEqualsWithDelta(6.0, $this->optionsOf('/content')['timeout'], 0.001);
        $this->microsoft = $prompt;
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $two], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());

        // A connection that could not be made in the 5 seconds a connection gets is Microsoft not answering, also with 6 seconds left ...
        [$one, $two] = $ids();
        $this->microsoft = ['children' => $slowList(9.5, 9.5), 'content' => self::timeout(connecting: true)] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertEqualsWithDelta([5, 6.0], [$this->optionsOf('/content')['connect_timeout'], $this->optionsOf('/content')['timeout']], 0.001);
        $this->microsoft = $prompt;
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $two], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count());

        // ... but not when the time left had made the time for the connection shorter than any request gets.
        [$one, $two] = $ids();
        $this->microsoft = ['children' => $slowList(9.5, 12.0), 'content' => self::timeout(connecting: true)] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertEqualsWithDelta([3.5, 3.5], [$this->optionsOf('/content')['connect_timeout'], $this->optionsOf('/content')['timeout']], 0.001);
        $this->microsoft = $prompt;
        $this->showFile(['id' => $two])->assertOk();

        // Nor when the request is set to take less than a connection gets: that is its own time, nothing the time left shortened.
        config(['sharepoint-docs.timeout' => 3]);
        [$one, $two] = $ids();
        $this->microsoft = ['content' => self::timeout(connecting: true)] + $prompt;
        $this->showFile(['id' => $one], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertEqualsWithDelta([3, 3], [$this->optionsOf('/content')['connect_timeout'], $this->optionsOf('/content')['timeout']], 0.001);
        $this->microsoft = $prompt;
        $asked = Http::recorded()->count();
        $this->showFile(['id' => $two], true)->assertStatus(503)->assertJsonPath('reason', 'unavailable');
        $this->assertSame($asked, Http::recorded()->count(), 'Microsoft did not answer within the time a request gets: nothing is asked for a while.');
        config(['sharepoint-docs.timeout' => 10]);

        // The client says which of the two it was, also for the transfer of a file: its own time
        // (preview_time_limit) passing with nothing arriving is the address not answering, unless
        // the reading it is part of had less than that left.
        $client = app(GraphClient::class);
        $address = self::DOWNLOAD . '?UniqueId=1&tempauth=one-time';
        $failure = function (callable $reading): array {
            try {
                $reading();
            } catch (GraphException $e) {
                return [$e->reason, $e->outOfTime];
            }

            return ['read', false];
        };
        $this->microsoft['download'] = $this->after(26, self::transfer(0, 0, fn () => Http::response(self::PDF), 0));
        $this->assertSame(['unavailable', false], $failure(fn () => $client->fetch($address, 1000)));
        $this->assertSame(['unavailable', true], $failure(fn () => $client->within(10, fn () => $client->fetch($address, 1000))));
        // The time for the transfer is the shorter of the two: its own, or what is left of the reading.
        $this->microsoft['download'] = $this->after(26, self::transfer(500, 100, fn () => Http::response(self::PDF)));
        $this->assertSame(['too_slow', false], $failure(fn () => $client->within(60, fn () => $client->fetch($address, 1000))));
        $this->microsoft['download'] = $this->after(11, self::transfer(500, 100, fn () => Http::response(self::PDF)));
        $this->assertSame(['too_slow', false], $failure(fn () => $client->within(10, fn () => $client->fetch($address, 1000))));
        $this->microsoft['download'] = $this->after(9, self::transfer(500, 500, fn () => Http::response(self::PDF)));
        $this->assertSame(['read', false], $failure(fn () => $client->within(10, fn () => $client->fetch($address, 1000))));
    }

    public function test_the_menu_decides_who_sees_the_folder(): void
    {
        $this->get(route('documents.cof-scan.index'))->assertRedirect(route('login'));
        $this->getJson(route('documents.cof-scan.list'))->assertUnauthorized();
        $this->get(route('documents.cof-scan.file', ['id' => 'ABC']))->assertRedirect(route('login'));

        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio');
        $viewer = $this->makeUser('viewer.radio@test.local', 'Viewer Radio');

        $this->actingAs($admin)->get(route('dashboard.index'))->assertOk()->assertSeeInOrder(['Dokumen', 'COF Scan']);
        $this->actingAs($admin)->get(route('documents.cof-scan.index'))->assertOk();
        $id = $this->actingAs($admin)->getJson(route('documents.cof-scan.list'))->assertOk()->assertJsonPath('meta.total', 3)->json('data.0.id');
        $file = route('documents.cof-scan.file', ['id' => $id]);
        $this->actingAs($admin)->get($file)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Viewer Radio is not given the folder by default: no page, no list, no file.
        $this->actingAs($viewer)->get(route('dashboard.index'))->assertOk()->assertDontSee('COF Scan');
        $this->actingAs($viewer)->get(route('documents.cof-scan.index'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('documents.cof-scan.list'))->assertForbidden();
        $this->actingAs($viewer)->get($file)->assertForbidden();

        // Ticked for the role without Read: the page opens, its list and its files stay closed.
        $grant = [
            'menu_id' => DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->value('id'),
            'role_id' => DB::table('roles')->where('name', 'Viewer Radio')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('menu_role')->insert($grant);
        $this->actingAs($viewer->fresh())->get(route('documents.cof-scan.index'))->assertOk();
        $this->actingAs($viewer->fresh())->getJson(route('documents.cof-scan.list'))->assertForbidden();
        $this->actingAs($viewer->fresh())->get($file)->assertForbidden();
        $this->assertSame(1, $this->sent('/content'), 'Only the granted role made the application fetch the file.');

        DB::table('menu_role_permission')->insert($grant + ['permission_id' => CheckMenuPermission::PERMISSION_IDS['Read']]);
        $this->actingAs($viewer->fresh())->get(route('dashboard.index'))->assertOk()->assertSee('COF Scan');
        $this->actingAs($viewer->fresh())->getJson(route('documents.cof-scan.list'))->assertOk();
        $this->actingAs($viewer->fresh())->get($file)->assertOk();

        $this->actingAs($admin)->get('/documents/folder-lain')->assertNotFound();
        $this->actingAs($admin)->get('/documents/folder-lain/file?id=' . $id)->assertNotFound();
    }

    public function test_without_its_menu_the_page_is_closed_instead_of_open_to_everyone(): void
    {
        $admin = $this->makeUser('admin.radio@test.local', 'Admin Radio');
        (require database_path(self::MIGRATION))->down();
        $this->assertFalse(DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->exists());

        // The permission middleware alone would let these through ("default open").
        $this->actingAs($admin)->get(route('documents.cof-scan.index'))->assertForbidden();
        $this->actingAs($admin)->getJson(route('documents.cof-scan.list'))->assertForbidden();
        $this->actingAs($admin)->get(route('documents.cof-scan.file', ['id' => strtoupper(md5('ICTCOF10 - Andi Wijaya.pdf'))]))->assertForbidden();
        Http::assertNothingSent();

        $this->actingAs($this->superAdmin())->getJson(route('documents.cof-scan.list'))->assertOk();
    }

    public function test_the_page_says_so_when_the_folder_or_the_application_is_not_configured(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['sharepoint-docs.folders.cof-scan.url' => '  ', 'services.azure.client_secret' => null] as $key => $missing) {
            $configured = config($key);
            config([$key => $missing]);

            $this->get(route('documents.cof-scan.index'))->assertOk()
                ->assertSee('belum bisa ditampilkan')
                ->assertDontSee('spDocs');
            $this->listing()->assertStatus(503)->assertJsonPath('reason', 'not_configured');
            $this->showFile(['id' => 'ABC'], true)->assertStatus(503)->assertJsonPath('reason', 'not_configured');

            config([$key => $configured]);
        }

        Http::assertNothingSent();
    }

    public function test_the_menu_is_added_once_and_is_then_the_owners(): void
    {
        $menu = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->sole();
        $this->assertSame(['COF Scan', 'Dokumen', 'files', null, 7], [$menu->title, $menu->section, $menu->icon, $menu->parent_id, (int) $menu->order]);
        // The route exists, and the folder key in it is not repeated as a query string.
        $this->assertSame('/documents/cof-scan', route(CofScanMenuSeeder::ROUTE, [], false));
        $this->assertSame('/documents/cof-scan/list', route('documents.cof-scan.list', [], false));

        $read = CheckMenuPermission::PERMISSION_IDS['Read'];
        $adminRole = (int) DB::table('roles')->where('name', 'Admin Radio')->value('id');
        $grants = fn (int $menuId) => [
            DB::table('menu_role')->where('menu_id', $menuId)->orderBy('role_id')->pluck('role_id')->map(fn ($id) => (int) $id)->all(),
            DB::table('menu_role_permission')->where('menu_id', $menuId)->orderBy('role_id')->get()
                ->map(fn ($grant) => [(int) $grant->role_id, (int) $grant->permission_id])->all(),
        ];
        // Super Admin and Admin Radio, read only; not Viewer Radio.
        $this->assertSame([[1, $adminRole], [[1, $read], [$adminRole, $read]]], $grants($menu->id));

        // The owner renames it and takes it away from Admin Radio. Migrating or seeding again leaves that alone.
        DB::table('menu')->where('id', $menu->id)->update(['title' => 'Arsip COF', 'order' => 2]);
        DB::table('menu_role')->where(['menu_id' => $menu->id, 'role_id' => $adminRole])->delete();
        DB::table('menu_role_permission')->where(['menu_id' => $menu->id, 'role_id' => $adminRole])->delete();

        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $this->seed(CofScanMenuSeeder::class);
        $this->seed();

        $kept = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->sole();
        $this->assertSame([$menu->id, 'Arsip COF', 2], [$kept->id, $kept->title, (int) $kept->order]);
        $this->assertSame([[1], [[1, $read]]], $grants($menu->id));

        // Rolled back, nothing of it is left; migrated again, it is back as it was first added.
        $migration->down();
        $this->assertFalse(DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->exists());
        $this->assertSame([[], []], $grants($menu->id));
        $migration->up();
        $again = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->sole();
        $this->assertSame('COF Scan', $again->title);
        $this->assertSame([[1, $adminRole], [[1, $read], [$adminRole, $read]]], $grants($again->id));
    }
}
