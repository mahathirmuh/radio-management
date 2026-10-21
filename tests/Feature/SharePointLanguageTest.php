<?php

namespace Tests\Feature;

use App\Models\Log\AuditLog;
use App\Models\Notification\UserNotification;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\CofScanMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as GraphRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Modules\SharePointDocs\Models\EditRequest;
use Modules\SharePointDocs\Services\XlsxPatcher;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\MakesWorkbooks;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * The pages of the SharePoint documents (COF Scan, ICT Masterlist, Persetujuan Edit) in English: their own words and
 * their answers follow the language that was chosen, while what is kept stays as it was (SharePoint's content, the
 * reason a change was not written, the audit log), and every notification is in the language of whoever gets it.
 * Microsoft is played by Http::fake(), as in SharePointEditTest.
 */
class SharePointLanguageTest extends TestCase
{
    use MakesUsers;
    use MakesWorkbooks;
    use RefreshDatabase;
    use UsesEnglish;

    protected bool $seed = true;

    private const FOLDER = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Forms/AllItems.aspx?id=%2Fsites%2FTeam%2FScans&viewid=1';

    private const WORKBOOK = 'https://contoso.sharepoint.com/:x:/r/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B11111111-2222-3333-4444-555555555555%7D&file=Daftar%20Induk.xlsx&action=default';

    private const DOWNLOAD = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/download.aspx';

    /** What the pretend SharePoint holds, and its version (changes with every save). */
    private string $bytes = '';

    private int $saves = 1;

    /** @var callable|null what answers a save: null saves it */
    private $onSave = null;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config([
            'logging.default' => 'null',
            'services.azure.tenant' => 'tenant-1',
            'services.azure.client_id' => 'client-1',
            'services.azure.client_secret' => 'secret-1',
            'sharepoint-docs.folders.cof-scan.url' => self::FOLDER,
            'sharepoint-docs.workbooks.ict-masterlist.url' => self::WORKBOOK,
            'sharepoint-docs.workbooks.ict-masterlist.sheets' => null,
            'sharepoint-docs.workbooks.ict-masterlist.skip_unused_rows' => false,
            'sharepoint-docs.workbooks.ict-masterlist.edit.enabled' => true,
            'sharepoint-docs.workbooks.ict-masterlist.edit.anchors' => [],
            'sharepoint-docs.cache_minutes' => 10,
        ]);
        $this->bytes = self::xlsx([
            'SRF' => [
                ['SRF No', 'Requested By', 'Serial Number'],
                ['ICT/SRF/1', 'Budi Santoso', '000123'],
                ['ICT/SRF/2', 'Andi Wijaya', 'SN-9'],
            ],
        ]);

        Http::fake(function (GraphRequest $request) {
            $url = $request->url();

            return match (true) {
                str_starts_with($url, 'https://login.microsoftonline.com/') => Http::response(['token_type' => 'Bearer', 'expires_in' => 3599, 'access_token' => 'token-1']),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/shares/') => Http::response($this->item()),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/drives/') && $request->method() === 'PUT' => $this->save($request),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/drives/') => Http::response('', 302, ['Location' => self::DOWNLOAD . '?UniqueId=1&tempauth=one-time']),
                str_starts_with($url, self::DOWNLOAD) => Http::response($this->bytes),
                default => Http::response('not Microsoft', 500),
            };
        });
    }

    private function item(): array
    {
        return [
            'id' => 'BOOK1',
            'name' => 'Daftar Induk.xlsx',
            'size' => strlen($this->bytes),
            'eTag' => '"{BOOK},' . $this->saves . '"',
            'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'lastModifiedDateTime' => '2026-10-06T07:01:28Z',
            'webUrl' => 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D',
            'parentReference' => ['driveId' => 'b!drive-1'],
        ];
    }

    private function save(GraphRequest $request)
    {
        if ($this->onSave !== null) {
            return ($this->onSave)($request);
        }
        $this->bytes = $request->body();
        $this->saves++;

        return Http::response($this->item());
    }

    private function admin(): User
    {
        return User::where('email', 'admin.radio@test.local')->first() ?? $this->makeUser('admin.radio@test.local', 'Admin Radio', 'Admin Radio Satu');
    }

    private function controller(): User
    {
        return User::where('email', 'doc.controller@test.local')->first() ?? $this->makeUser('doc.controller@test.local', 'Document Controller', 'Doc Controller Satu');
    }

    /** The language the account chose with the switch. */
    private static function speaks(User $user, ?string $locale): User
    {
        DB::table('users')->where('id', $user->id)->update(['locale' => $locale]);

        return $user->fresh();
    }

    private function ask(int $line, int $column, string $value): TestResponse
    {
        return $this->patchJson(route('documents.ict-masterlist.edit'), ['sheet' => 0, 'line' => $line, 'column' => $column, 'value' => $value]);
    }

    private function approve(EditRequest $request): TestResponse
    {
        return $this->patchJson(route('documents.edit-approvals.approve', $request->id));
    }


    public function test_the_three_pages_are_in_english_when_it_is_chosen(): void
    {
        $this->actingAs($this->superAdmin());
        $this->useEnglish();

        $this->get(route('documents.cof-scan.index'))->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Scans of the IT Equipment Checkout Form (COF) in the ICT SharePoint. Read from SharePoint, refreshed every 10 minutes.')
            ->assertSee('<th data-key="name" width="130">COF No.</th>', false)
            ->assertSee('placeholder="COF No. or name…"', false)
            ->assertSee('Show all files')
            ->assertSee('Open in SharePoint')
            ->assertSee('"no_documents":"No documents"', false)
            ->assertSee('"session_ended":"Your session has ended. Reload the page to sign in again."', false)
            ->assertDontSee('Tampilkan semua file')
            ->assertDontSee('No. COF')
            ->assertDontSee('Muat ulang');

        $this->get(route('documents.ict-masterlist.index'))->assertOk()
            ->assertSee('Register of ICT documents (SRF, COF, handovers and more) from an Excel workbook in the ICT SharePoint.')
            ->assertSee('This page only shows the data; changes are still made in Excel.')
            ->assertSee('Search all columns')
            ->assertSee('Ask for a change of a cell')
            ->assertSee('<b>Found</b>: a file with the same name as in the link', false)
            ->assertSee('"statuses":{"ok":"Found","missing":"Missing","differs":"Different name","below":"Subfolder"}', false)
            ->assertSee('"refusals":["Anda tidak punya akses ke menu ini.","Anda tidak diizinkan melakukan aksi ini.","You do not have access to this menu.","You are not allowed to perform this action."]', false)
            ->assertDontSee('Cari di semua kolom')
            ->assertDontSee('Ajukan perubahan sel')
            ->assertDontSee('Halaman ini hanya menampilkan');

        $this->get(route('documents.edit-approvals.index'))->assertOk()
            ->assertSee('<h1 class="rm-title">Edit Approvals</h1>', false)
            ->assertSee('Search the requests')
            ->assertSee('"pending":"Pending"', false)
            ->assertSee('"cleared":"(emptied)"', false)
            ->assertDontSee('Persetujuan Edit')
            ->assertDontSee('Cari pengajuan');

        // An answer of the folder's list, and the page shown in the viewer when a file cannot be shown.
        $this->getJson(route('documents.cof-scan.list', ['path' => '../x']))->assertStatus(422)->assertExactJson(['message' => 'Unknown folder.']);
        $this->get(route('documents.cof-scan.file', ['path' => '../x', 'id' => 'X']))->assertStatus(422)
            ->assertSee('<title>The file cannot be shown</title>', false)
            ->assertSee('Unknown file.');
    }

    public function test_in_indonesian_by_default_the_pages_read_as_they_always_did(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('documents.cof-scan.index'))->assertOk()
            ->assertSee('Scan IT Equipment Checkout Form (COF) di SharePoint ICT. Dibaca dari SharePoint, diperbarui tiap 10 menit.')
            ->assertSee('placeholder="No. COF atau nama…"', false)
            ->assertSee('Tampilkan semua file');
        $this->get(route('documents.ict-masterlist.index'))->assertOk()
            ->assertSee('Cari di semua kolom')
            ->assertSee('"statuses":{"ok":"Ada","missing":"Belum ada","differs":"Nama beda","below":"Subfolder"}', false);
        $this->get(route('documents.edit-approvals.index'))->assertOk()
            ->assertSee('<h1 class="rm-title">Persetujuan Edit</h1>', false)
            ->assertSee('"pending":"Menunggu"', false);

        // Words of the config that someone changed are shown as they are now, in both languages.
        config(['sharepoint-docs.folders.cof-scan.subtitle' => 'Folder scan form serah terima']);
        $this->get(route('documents.cof-scan.index'))->assertSee('Folder scan form serah terima. Dibaca dari SharePoint');
        $this->useEnglish();
        $this->get(route('documents.cof-scan.index'))->assertSee('Folder scan form serah terima. Read from SharePoint');
    }

    public function test_a_page_that_has_no_menu_yet_says_why_in_the_language_chosen(): void
    {
        $menu = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->value('id');
        DB::table('menu_role_permission')->where('menu_id', $menu)->delete();
        DB::table('menu_role')->where('menu_id', $menu)->delete();
        DB::table('menu')->where('id', $menu)->delete();
        $this->useEnglish();

        $this->actingAs($this->admin())->get(route('documents.cof-scan.index'))->assertForbidden()
            ->assertSee('This page has not been added to the menu yet.');
    }

    public function test_a_change_is_answered_in_the_language_of_the_page_and_kept_as_it_always_was(): void
    {
        $this->useEnglish();
        $this->actingAs($this->admin());
        $this->ask(2, 1, 'Budi S.')->assertCreated()
            ->assertJsonPath('message', 'The change of cell B2 has been asked for. The workbook changes only after it is approved.');
        $this->ask(2, 1, 'Budi')->assertStatus(409)
            ->assertJsonPath('message', 'A request for cell B2 is already waiting for a decision (asked for by Admin Radio Satu).');
        $this->ask(3, 1, 'Andi Wijaya')->assertStatus(422)->assertJsonPath('message', 'The new value is the same as the current one.');
        $this->ask(3, 2, '')->assertCreated();
        $request = EditRequest::query()->where('cell', 'B2')->sole();

        // The rows say what a waiting cell is to hold, an emptied one in English.
        $this->actingAs($this->superAdmin());
        $this->getJson(route('documents.ict-masterlist.rows', ['sort' => 'row', 'dir' => 'asc']))->assertOk()
            ->assertJsonPath('data.0.waiting.1', 'Budi S.')
            ->assertJsonPath('data.1.waiting.2', '(emptied)');

        // Changed in SharePoint meanwhile by somebody else: not written. The approver is told in English; what is kept with
        // the request, and in the audit log, is Indonesian as before.
        $this->bytes = (new XlsxPatcher())->write($this->bytes, 'SRF', 'B2', 'Lain');
        $this->saves++;
        $requester = self::speaks($this->admin(), 'en');
        $this->actingAs($this->controller());
        $this->approve($request)->assertStatus(409)->assertJsonPath('status', 'conflict')
            ->assertJsonPath('message', 'Cell B2 has changed in SharePoint since the request (now: "Lain"). Nothing was written; ask again.');
        $this->assertSame('Isi sel B2 sudah berubah di SharePoint sejak diajukan (sekarang: "Lain"). Tidak ada yang ditulis; ajukan lagi.', $request->fresh()->failure);
        $audit = AuditLog::query()->where('action', AuditLogger::EDIT_APPROVED)->latest('id')->first();
        $this->assertSame('Konflik', $audit->new_values['Result']);

        // Whoever asked chose English: told so, with the reason in English.
        $told = UserNotification::query()->where('user_id', $requester->id)->sole();
        $this->assertSame(['Your change was not applied', 'SRF B2 (Requested By, ICT/SRF/1): Cell B2 has changed in SharePoint since the request (now: "Lain"). Nothing was written; ask again.'],
            [$told->title, $told->body]);

        // The list: the status in English, the reason as it was kept.
        $this->getJson(route('documents.edit-approvals.list', ['status' => 'conflict']))->assertOk()
            ->assertJsonPath('data.0.status_label', 'Conflict')
            ->assertJsonPath('data.0.failure', $request->fresh()->failure);
        $this->patchJson(route('documents.edit-approvals.reject', $request->id))->assertOk()
            ->assertJsonPath('message', 'The request to change cell B2 has been rejected.');
    }

    public function test_every_notification_is_in_the_language_of_whoever_gets_it(): void
    {
        $super = $this->superAdmin();
        $controller = self::speaks($this->controller(), 'en');
        // The account's choice is the language of its pages, whatever the browser says.
        $this->withUnencryptedCookie('locale', 'id')->withCredentials();
        $this->actingAs(self::speaks($this->admin(), 'en'));

        // Emptying a cell: each approver reads it in their language (Super Admin has not chosen one: Indonesian).
        $this->ask(2, 2, '')->assertCreated()->assertJsonPath('message', 'The change of cell C2 has been asked for. The workbook changes only after it is approved.');
        $request = EditRequest::sole();
        $english = UserNotification::query()->where('user_id', $controller->id)->sole();
        $this->assertSame(['A change is waiting for approval', 'Admin Radio Satu asks to change SRF C2 (Serial Number, ICT/SRF/1): "000123" → "(emptied)".'],
            [$english->title, $english->body]);
        $indonesian = UserNotification::query()->where('user_id', $super->id)->sole();
        $this->assertSame(['Pengajuan perubahan menunggu persetujuan', 'Admin Radio Satu mengajukan SRF C2 (Serial Number, ICT/SRF/1): "000123" → "(dikosongkan)".'],
            [$indonesian->title, $indonesian->body]);

        // The file is open in Excel: not written. Super Admin approves in Indonesian; whoever asked is told in English.
        $this->onSave = fn () => Http::response(['error' => ['code' => 'resourceLocked', 'message' => 'The file is locked.']], 423);
        $this->actingAs($super);
        $this->approve($request)->assertStatus(502)
            ->assertJsonPath('message', 'Workbook sedang dikunci karena dibuka orang lain di Excel. Tidak ada yang ditulis; coba lagi beberapa saat lagi.');
        $told = UserNotification::query()->where('user_id', $this->admin()->id)->sole();
        $this->assertSame(['Your change could not be applied yet', 'SRF C2 (Serial Number, ICT/SRF/1): The workbook is locked because somebody has it open in Excel. Nothing was written; try again in a moment.'],
            [$told->title, $told->body]);
    }
}
