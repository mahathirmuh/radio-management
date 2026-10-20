<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\Log\AuditLog;
use App\Models\Notification\UserNotification;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\DocumentEditAccessSeeder;
use Database\Seeders\EditApprovalsMenuSeeder;
use Database\Seeders\IctMasterlistMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as GraphRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Modules\SharePointDocs\Models\EditRequest;
use Modules\SharePointDocs\Services\XlsxPatcher;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\MakesWorkbooks;
use Tests\TestCase;
use ZipArchive;

/**
 * Changing a cell of the ICT Masterlist workbook: asked for by one role, approved by another, and only then written
 * to SharePoint. Microsoft is played by Http::fake(): the workbook it holds is made here, and what is put back into
 * it is kept, so that the tests can read what was written.
 */
class SharePointEditTest extends TestCase
{
    use MakesUsers;
    use MakesWorkbooks;
    use RefreshDatabase;

    protected bool $seed = true;

    private const ADDRESS = 'https://contoso.sharepoint.com/:x:/r/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B11111111-2222-3333-4444-555555555555%7D&file=Daftar%20Induk.xlsx&action=default';

    private const DOWNLOAD = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/download.aspx';

    /** What the pretend SharePoint holds, and its version (changes with every save). */
    private string $bytes = '';

    private int $saves = 1;

    /** @var array<int, array{bytes: string, ifMatch: ?string}> everything that was put into the workbook */
    private array $saved = [];

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
            'sharepoint-docs.workbooks.ict-masterlist.url' => self::ADDRESS,
            'sharepoint-docs.workbooks.ict-masterlist.sheets' => null,
            'sharepoint-docs.workbooks.ict-masterlist.skip_unused_rows' => false,
            'sharepoint-docs.workbooks.ict-masterlist.edit.enabled' => true,
            'sharepoint-docs.workbooks.ict-masterlist.edit.anchors' => [],
            'sharepoint-docs.cache_minutes' => 10,
            'app.timezone' => 'Asia/Makassar',
        ]);
        $this->bytes = self::xlsx(self::book());

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
            'eTag' => $this->version(),
            'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'lastModifiedDateTime' => '2026-10-06T07:01:28Z',
            'webUrl' => 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D',
            'parentReference' => ['driveId' => 'b!drive-1'],
        ];
    }

    private function version(): string
    {
        return '"{BOOK},' . $this->saves . '"';
    }

    /** The pretend SharePoint saves what is put into it, unless the test says what it answers instead. */
    private function save(GraphRequest $request)
    {
        $this->saved[] = ['bytes' => $request->body(), 'ifMatch' => $request->header('If-Match')[0] ?? null];
        if ($this->onSave !== null) {
            return ($this->onSave)($request);
        }
        $this->bytes = $request->body();
        $this->saves++;

        return Http::response($this->item());
    }

    /** Somebody else saves the workbook with a cell changed (not through this application). */
    private function savedByOther(string $sheet, string $cell, string|float|null $value): void
    {
        $this->bytes = (new XlsxPatcher())->write($this->bytes, $sheet, $cell, $value);
        $this->saves++;
    }

    private static function book(): array
    {
        return [
            'SRF' => [
                ['SRF No', 'Date', 'Requested By', 'Qty', 'Value', 'Serial Number', 'Check'],
                ['ICT/SRF/1', ['value' => 45000, 'format' => 'd-mmm-yy'], 'Budi Santoso', 2, ['value' => 1500000, 'format' => '#,##0'], '000123', ['formula' => '=D2*2']],
                ['ICT/SRF/2', ['value' => 45001.5, 'format' => 'dd/mm/yyyy hh:mm'], 'Andi Wijaya', 10, ['value' => 250000.5, 'format' => '#,##0.00'], 'SN-9', null],
                ['ICT/SRF/10', null, 'Citra Dewi', null, null, null, null],
            ],
            'Lain' => [
                ['A', 'B'],
                ['x', 'y'],
            ],
        ];
    }

    private function admin(): User
    {
        return User::where('email', 'admin.radio@test.local')->first() ?? $this->makeUser('admin.radio@test.local', 'Admin Radio', 'Admin Radio Satu');
    }

    private function controller(): User
    {
        return User::where('email', 'doc.controller@test.local')->first() ?? $this->makeUser('doc.controller@test.local', 'Document Controller', 'Doc Controller Satu');
    }

    private function viewer(): User
    {
        return User::where('email', 'viewer.radio@test.local')->first() ?? $this->makeUser('viewer.radio@test.local', 'Viewer Radio', 'Viewer Satu');
    }

    /** Asks for the change of a cell as the page does: the sheet by its position, the row by its number in Excel, the column by its place in the table. */
    private function ask(array $cell, string $value, int $sheet = 0): TestResponse
    {
        [$line, $column] = $cell;

        return $this->patchJson(route('documents.ict-masterlist.edit'), ['sheet' => $sheet, 'line' => $line, 'column' => $column, 'value' => $value]);
    }

    private function approve(EditRequest|int $request): TestResponse
    {
        return $this->patchJson(route('documents.edit-approvals.approve', $request instanceof EditRequest ? $request->id : $request));
    }

    private function written(string $sheet, string $cell): ?string
    {
        return (new XlsxPatcher())->read($this->bytes, $sheet, $cell)['raw'];
    }

    private function puts(): int
    {
        return Http::recorded(fn (GraphRequest $request) => $request->method() === 'PUT')->count();
    }

    // ================================================================ who may ask

    public function test_the_menu_decides_who_may_ask_for_a_change(): void
    {
        // Somebody who may only read gets the page as it was: no pencils, and no way to ask.
        $viewer = $this->viewer();
        IctMasterlistMenuSeeder::grantRead((int) DB::table('roles')->where('name', 'Viewer Radio')->value('id'));
        $this->actingAs($viewer->fresh());
        $this->getJson(route('documents.ict-masterlist.rows'))->assertOk()
            ->assertJsonPath('sheet.editable_columns', 0)
            ->assertJsonMissingPath('data.0.locked');
        $this->ask([2, 2], 'Budi S.')->assertForbidden();
        $this->assertSame(0, EditRequest::count());

        // Super Admin, Admin Radio and the Document Controller may ask: the page offers it, and says which cells hold a formula.
        foreach ([$this->superAdmin(), $this->admin(), $this->controller()] as $user) {
            $this->actingAs($user->fresh());
            $this->getJson(route('documents.ict-masterlist.rows', ['sort' => 'row', 'dir' => 'asc']))->assertOk()
                ->assertJsonPath('sheet.editable_columns', 7)
                ->assertJsonPath('data.0.line', 2)
                ->assertJsonPath('data.0.locked', [6])
                ->assertJsonMissingPath('data.1.locked');
        }

        // Nothing of this wrote to SharePoint.
        $this->assertSame(0, $this->puts());
    }

    public function test_before_the_migration_has_run_nothing_is_offered_and_nothing_fails(): void
    {
        // The code of this folder is live as soon as it is saved; the migration is run by hand, later.
        Schema::drop('sharepoint_edit_requests');
        Cache::forget('sharepoint-edit-requests-table');

        $this->actingAs($this->superAdmin());
        $this->getJson(route('documents.ict-masterlist.rows'))->assertOk()
            ->assertJsonPath('sheet.editable_columns', 0)->assertJsonMissingPath('data.0.locked')->assertJsonMissingPath('data.0.waiting');
        $this->ask([2, 2], 'Budi S.')->assertStatus(503)->assertJsonPath('message', fn ($message) => str_contains($message, 'php artisan migrate'));
        $this->getJson(route('documents.edit-approvals.list'))->assertStatus(503)->assertJsonPath('message', fn ($message) => str_contains($message, 'migrate'));
        $this->get(route('documents.edit-approvals.index'))->assertOk();
        $this->get(route('documents.ict-masterlist.index'))->assertOk();
        $this->assertSame(0, $this->puts());
    }

    public function test_asking_changes_nothing_in_the_workbook_and_waits_for_a_decision(): void
    {
        $this->actingAs($this->admin());

        $this->ask([2, 2], '  Budi   S.  ')->assertCreated()
            ->assertJsonPath('cell', 'C2')->assertJsonPath('status', 'pending');

        $request = EditRequest::sole();
        $this->assertSame(
            ['ict-masterlist', 'SRF', 'C2', 2, 'Requested By', 'A2', 'ICT/SRF/1', 'Budi Santoso', null, 'text', 'Budi S.', null, 'pending'],
            [$request->workbook, $request->sheet, $request->cell, $request->line, $request->column_title, $request->anchor_cell, $request->anchor_value,
                $request->old_text, $request->old_number, $request->new_kind, $request->new_value, $request->new_number, $request->status]
        );
        $this->assertSame([$this->admin()->id, 'Admin Radio Satu'], [$request->requested_by, $request->requested_by_name]);

        $this->assertSame(0, $this->puts());
        $this->assertSame('Budi Santoso', $this->written('SRF', 'C2'));

        // The cell shows that it waits; for the one who may ask only.
        $this->getJson(route('documents.ict-masterlist.rows', ['sort' => 'row', 'dir' => 'asc']))
            ->assertJsonPath('data.0.waiting', ['2' => 'Budi S.']);
        $this->actingAs($this->viewer());
        $this->getJson(route('documents.ict-masterlist.rows'))->assertJsonMissingPath('data.0.waiting');
    }

    public function test_what_is_typed_becomes_what_the_cell_holds(): void
    {
        $this->actingAs($this->admin());
        $asked = function (array $cell, string $value): EditRequest {
            $this->ask($cell, $value)->assertCreated();

            return EditRequest::latest('id')->first();
        };

        // A cell that holds a number: a number, typed the Indonesian way or plainly.
        $number = $asked([2, 4], '1.600.000,5');
        $this->assertSame(['number', 1600000.5, '1.600.000,5'], [$number->new_kind, $number->new_number, $number->new_value]);
        // Nothing of it is lost on the way: a number of 15 digits comes back as it went in (a float column would give 14).
        $long = $asked([2, 3], '352099001761481');
        $this->assertSame(352099001761481.0, $long->new_number);
        $this->assertSame('352099001761481', DB::table('sharepoint_edit_requests')->where('id', $long->id)->value('new_number'));
        EditRequest::whereKey($long->id)->delete();
        $this->assertSame(['number', 12.0], [$asked([2, 3], '12')->new_kind, EditRequest::latest('id')->first()->new_number]);

        // A date, as it is shown: 15-03-2023 is day 45000, and a time of day is a part of one.
        $date = $asked([2, 1], '20-03-2023');
        $this->assertSame(['number', 45005.0, '20-03-2023'], [$date->new_kind, $date->new_number, $date->new_value]);
        $dated = $asked([3, 1], '3-4-2023 18:30');
        $this->assertSame(['number', '03-04-2023 18:30'], [$dated->new_kind, $dated->new_value]);
        $this->assertEqualsWithDelta(45019.0 + 18.5 / 24, $dated->new_number, 1e-9);

        // Text stays text, with the zeros in front of it.
        $text = $asked([2, 5], '000999');
        $this->assertSame(['text', '000999', null], [$text->new_kind, $text->new_value, $text->new_number]);

        // An empty cell: an ordinary number is a number (as Excel has it), anything else text; nothing typed empties a cell.
        $this->assertSame('number', $asked([4, 3], '7')->new_kind);
        $this->assertSame('text', $asked([4, 5], '007')->new_kind);
        $this->assertSame('text', $asked([4, 4], '1.5')->new_kind);
        $empty = $asked([3, 2], '');
        $this->assertSame(['empty', null], [$empty->new_kind, $empty->new_value]);
    }

    public function test_a_change_that_cannot_be_made_is_refused_with_the_reason(): void
    {
        $this->actingAs($this->admin());

        // A cell with a formula; the column that is no cell (the check against a folder is one beyond the last); a row or sheet that is not there.
        $this->ask([2, 6], '9')->assertStatus(422)->assertJsonPath('message', 'Sel ini berisi rumus. Ubah di Excel.');
        $this->ask([2, 7], '9')->assertStatus(422)->assertJsonPath('message', 'Kolom ini tidak bisa diubah.');
        $this->ask([99, 2], 'x')->assertStatus(422);
        $this->ask([2, 2], 'x', 9)->assertStatus(422);

        // Nothing changed; a number column with words in it; a date column with something else; text that cannot be kept.
        $this->ask([2, 2], 'Budi  Santoso')->assertStatus(422)->assertJsonPath('message', 'Nilai baru sama dengan nilai sekarang.');
        $this->ask([2, 3], 'banyak')->assertStatus(422)->assertJsonPath('message', fn ($message) => str_starts_with($message, 'Kolom ini berisi angka'));
        // More digits than Excel keeps of a number: refused, not cut off.
        $this->ask([2, 3], '3520990017614812')->assertStatus(422);
        $this->ask([2, 1], '31-02-2023')->assertStatus(422)->assertJsonPath('message', fn ($message) => str_contains($message, 'hh-bb-tttt'));
        $this->ask([2, 2], "kontrol \x01 karakter")->assertStatus(422);
        $this->ask([2, 2], str_repeat('a', 1001))->assertStatus(422);
        $this->patchJson(route('documents.ict-masterlist.edit'), ['sheet' => 'a', 'line' => 0, 'column' => -1])->assertStatus(422);

        // Where changing is switched off.
        config(['sharepoint-docs.workbooks.ict-masterlist.edit.enabled' => false]);
        $this->ask([2, 2], 'Budi S.')->assertForbidden();
        $this->getJson(route('documents.ict-masterlist.rows'))->assertJsonPath('sheet.editable_columns', 0);

        $this->assertSame(0, EditRequest::count());
    }

    public function test_a_cell_has_one_request_at_a_time(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();

        $this->actingAs($this->controller());
        $this->ask([2, 2], 'Budi Lain')->assertStatus(409)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'C2') && str_contains($message, 'Admin Radio Satu'));
        $this->assertSame(1, EditRequest::count());

        // Another cell of the same row is another request.
        $this->ask([2, 5], 'SN-1')->assertCreated();
        $this->assertSame(2, EditRequest::count());
    }

    // ================================================================ who decides

    public function test_an_approved_change_is_written_to_the_one_cell_and_is_on_the_record(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();
        $before = $this->bytes;
        $versionBefore = $this->version();

        $this->actingAs($this->controller());
        $this->approve($request)->assertOk()->assertJsonPath('status', 'applied');

        // Put back in place of the file as it was, only if nobody had saved it since (its version goes with it).
        $this->assertSame(1, $this->puts());
        $this->assertSame($versionBefore, $this->saved[0]['ifMatch']);
        $this->assertSame('Budi S.', $this->written('SRF', 'C2'));
        // Nothing else of the workbook changed: not the other cells, not the other sheet.
        $patcher = new XlsxPatcher();
        foreach (['A2', 'B2', 'D2', 'E2', 'F2', 'C3', 'C4'] as $cell) {
            $this->assertSame($patcher->read($before, 'SRF', $cell)['raw'], $patcher->read($this->bytes, 'SRF', $cell)['raw'], $cell);
        }
        $this->assertTrue($patcher->read($this->bytes, 'SRF', 'G2')['formula']);
        $this->assertSame('y', $this->written('Lain', 'B2'));

        $request->refresh();
        $this->assertSame(['applied', 'Doc Controller Satu', $this->controller()->id, null], [$request->status, $request->decided_by_name, $request->decided_by, $request->failure]);
        $this->assertNotNull($request->applied_at);
        $this->assertSame('Admin Radio Satu', $request->requested_by_name);

        // In the audit trail: who asked for what, who approved it, and what came of it.
        $label = config('sharepoint-docs.workbooks.ict-masterlist.title') . ' · SRF!C2';
        $asked = AuditLog::where('action', AuditLogger::EDIT_REQUESTED)->sole();
        $this->assertSame([$this->admin()->id, 'EditRequest', $request->id, $label, 'Budi S.'],
            [(int) $asked->user_id, $asked->entity_type, (int) $asked->entity_id, $asked->entity_label, $asked->new_values['Value']]);
        $approved = AuditLog::where('action', AuditLogger::EDIT_APPROVED)->sole();
        $this->assertSame([$this->controller()->id, $label, ['Value' => 'Budi S.', 'Result' => 'Diterapkan']],
            [(int) $approved->user_id, $approved->entity_label, $approved->new_values]);

        // The page shows the new content at once.
        $this->actingAs($this->superAdmin());
        $this->getJson(route('documents.ict-masterlist.rows', ['sort' => 'row', 'dir' => 'asc']))
            ->assertJsonPath('data.0.cells.2', 'Budi S.')->assertJsonMissingPath('data.0.waiting');
    }

    public function test_numbers_dates_and_emptied_cells_are_written_as_what_they_are(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 4], '1.600.000,5')->assertCreated();
        $this->ask([2, 1], '20-03-2023')->assertCreated();
        $this->ask([3, 2], '')->assertCreated();
        $this->ask([2, 5], '000999')->assertCreated();
        $this->ask([4, 3], '7')->assertCreated();

        $this->actingAs($this->controller());
        foreach (EditRequest::orderBy('id')->get() as $request) {
            $this->approve($request)->assertOk();
        }

        $patcher = new XlsxPatcher();
        $this->assertSame('1600000.5', $this->written('SRF', 'E2'));
        $this->assertSame('45005', $this->written('SRF', 'B2'));
        $this->assertNull($this->written('SRF', 'C3'));
        $this->assertSame('000999', $this->written('SRF', 'F2'));
        $this->assertSame('n', $patcher->read($this->bytes, 'SRF', 'D4')['type']);
        $this->assertSame('7', $this->written('SRF', 'D4'));
        $this->assertSame(5, $this->puts());

        // Shown as the workbook shows a number and a date.
        $this->actingAs($this->superAdmin());
        $this->getJson(route('documents.ict-masterlist.rows', ['sort' => 'row', 'dir' => 'asc']))
            ->assertJsonPath('data.0.cells', ['ICT/SRF/1', '20-03-2023', 'Budi Santoso', '2', '1.600.001', '000999', '4']);
    }

    public function test_nobody_approves_their_own_request_and_the_menu_decides_who_approves(): void
    {
        // Admin Radio sees the requests, but may not decide on them: the route needs Update.
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();
        $this->approve($request)->assertForbidden();
        $this->patchJson(route('documents.edit-approvals.reject', $request->id))->assertForbidden();
        $this->getJson(route('documents.edit-approvals.list'))->assertOk()->assertJsonPath('may_decide', false)
            ->assertJsonPath('data.0.can_approve', false)->assertJsonPath('data.0.can_reject', false);

        // The Document Controller may, but not for their own request; nor may Super Admin for theirs.
        $this->actingAs($this->controller());
        $own = $this->ask([3, 2], 'Andi W.');
        $own->assertCreated();
        $mine = EditRequest::where('cell', 'C3')->sole();
        $this->approve($mine)->assertForbidden()->assertJsonPath('message', fn ($message) => str_contains($message, 'sendiri'));
        $this->getJson(route('documents.edit-approvals.list', ['status' => 'pending']))->assertOk()->assertJsonPath('may_decide', true)
            ->assertJsonPath('data.0.cell', 'C3')->assertJsonPath('data.0.mine', true)->assertJsonPath('data.0.can_approve', false)
            ->assertJsonPath('data.1.cell', 'C2')->assertJsonPath('data.1.can_approve', true);

        $this->actingAs($this->superAdmin());
        $this->ask([4, 2], 'Citra D.')->assertCreated();
        $this->approve(EditRequest::where('cell', 'C4')->sole())->assertForbidden();
        // ... but each approves the other's: Super Admin the Document Controller's, the Document Controller Super Admin's.
        $this->approve($mine)->assertOk()->assertJsonPath('status', 'applied');
        $this->actingAs($this->controller());
        $this->approve(EditRequest::where('cell', 'C4')->sole())->assertOk();
        $this->assertSame(['Andi W.', 'Citra D.'], [$this->written('SRF', 'C3'), $this->written('SRF', 'C4')]);

        // The one of Admin Radio waited all the while; nothing was written for it.
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('Budi Santoso', $this->written('SRF', 'C2'));
    }

    public function test_a_request_is_decided_once(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        $this->actingAs($this->controller());
        $this->approve($request)->assertOk();
        $this->approve($request)->assertStatus(409);
        $this->patchJson(route('documents.edit-approvals.reject', $request->id))->assertStatus(409);
        $this->assertSame(1, $this->puts());

        // Somebody else is writing it just now.
        $this->actingAs($this->admin());
        $this->ask([3, 2], 'Andi W.')->assertCreated();
        $this->actingAs($this->controller());
        EditRequest::where('cell', 'C3')->update(['status' => 'processing', 'updated_at' => now()]);
        $this->approve(EditRequest::where('cell', 'C3')->sole())->assertStatus(409);
        // ... unless that was long ago: the one who was writing it is gone.
        EditRequest::where('cell', 'C3')->update(['updated_at' => now()->subMinutes(10)]);
        $this->approve(EditRequest::where('cell', 'C3')->sole())->assertOk();
    }

    public function test_a_request_can_be_turned_down_with_a_reason(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        $this->actingAs($this->controller());
        $this->patchJson(route('documents.edit-approvals.reject', $request->id), ['note' => "  Nama\nsalah  "])->assertOk()->assertJsonPath('status', 'rejected');

        $request->refresh();
        $this->assertSame(['rejected', 'Nama salah', 'Doc Controller Satu'], [$request->status, $request->decision_note, $request->decided_by_name]);
        $this->assertSame(0, $this->puts());
        $rejected = AuditLog::where('action', AuditLogger::EDIT_REJECTED)->sole();
        $this->assertSame([$this->controller()->id, ['Value' => 'Budi S.', 'Note' => 'Nama salah']], [(int) $rejected->user_id, $rejected->new_values]);
        $this->approve($request)->assertStatus(409);
        // Refused (decided already): nothing more in the trail.
        $this->assertFalse(AuditLog::where('action', AuditLogger::EDIT_APPROVED)->exists());

        // The cell is free for a new request.
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
    }

    // ================================================================ what happens to the workbook

    public function test_a_cell_that_was_changed_meanwhile_is_not_written_over(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        // Somebody changed the very cell in Excel.
        $this->savedByOther('SRF', 'C2', 'Budi Santoso Putra');

        $this->actingAs($this->controller());
        $this->approve($request)->assertStatus(409)->assertJsonPath('status', 'conflict');

        $request->refresh();
        $this->assertSame('conflict', $request->status);
        $this->assertStringContainsString('Budi Santoso Putra', (string) $request->failure);
        $this->assertSame(0, $this->puts());
        $this->assertSame('Budi Santoso Putra', $this->written('SRF', 'C2'));

        // It is not offered for approval again; it can be turned down.
        $this->getJson(route('documents.edit-approvals.list'))->assertJsonPath('data.0.can_approve', false)->assertJsonPath('data.0.can_reject', true);
        $this->approve($request)->assertStatus(409);
    }

    public function test_a_row_that_moved_is_not_written_to(): void
    {
        $this->actingAs($this->admin());
        $this->ask([3, 2], 'Andi W.')->assertCreated();
        $request = EditRequest::sole();

        // A row was put in above it in Excel: what is at line 3 now is another document (the cell itself holds what it did).
        $this->savedByOther('SRF', 'A3', 'ICT/SRF/99');

        $this->actingAs($this->controller());
        $this->approve($request)->assertStatus(409)->assertJsonPath('status', 'conflict');
        $this->assertStringContainsString('Baris 3', (string) $request->fresh()->failure);
        $this->assertSame(0, $this->puts());
    }

    public function test_a_cell_that_became_a_formula_or_a_sheet_that_went_is_not_written_to(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $this->ask([2, 5], 'SN-1')->assertCreated();
        $this->actingAs($this->controller());

        // The sheet was renamed.
        $request = EditRequest::where('cell', 'C2')->sole();
        $request->update(['sheet' => 'Sudah diganti']);
        $this->approve($request)->assertStatus(409);
        $this->assertStringContainsString('Sudah diganti', (string) $request->fresh()->failure);

        // A formula where there was text: the file has one now (written here the way Excel would).
        $this->bytes = self::xlsx(['SRF' => [
            ['SRF No', 'Date', 'Requested By', 'Qty', 'Value', 'Serial Number', 'Check'],
            ['ICT/SRF/1', ['value' => 45000, 'format' => 'd-mmm-yy'], 'Budi Santoso', 2, ['value' => 1500000, 'format' => '#,##0'], ['formula' => '="000123"'], null],
        ]]);
        $this->saves++;
        $this->approve(EditRequest::where('cell', 'F2')->sole())->assertStatus(409);
        $this->assertStringContainsString('rumus', (string) EditRequest::where('cell', 'F2')->sole()->failure);
        $this->assertSame(0, $this->puts());
    }

    public function test_a_workbook_saved_by_somebody_else_in_the_moment_of_writing_is_not_written_over(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        // SharePoint refuses the save (the version is not the one that was read) because another cell was saved in between.
        $this->onSave = function () {
            $this->savedByOther('SRF', 'C3', 'Andi Lain');

            return Http::response(['error' => ['code' => 'preconditionFailed', 'message' => 'The ETag does not match.']], 412);
        };

        $this->actingAs($this->controller());
        // The file is read again: the cell is still as it was, so nothing was lost, but this request did not make it: not applied, not a failure of SharePoint.
        $this->approve($request)->assertStatus(409)->assertJsonPath('status', 'conflict');
        $this->assertSame('Budi Santoso', $this->written('SRF', 'C2'));
        $this->assertSame('Andi Lain', $this->written('SRF', 'C3'));
        // It can be approved again: the next time the new version is read, and written.
        $this->onSave = null;
        $this->assertSame('conflict', $request->fresh()->status);
    }

    public function test_a_save_that_went_through_after_all_is_not_taken_for_a_conflict(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        // The first save is made and its answer lost; asked a second time, SharePoint says the version is not the one that was sent.
        $this->onSave = function (GraphRequest $put) {
            $this->bytes = $put->body();
            $this->saves++;

            return Http::response(['error' => ['code' => 'preconditionFailed', 'message' => 'The ETag does not match.']], 412);
        };

        $this->actingAs($this->controller());
        $this->approve($request)->assertOk()->assertJsonPath('status', 'applied');
        $this->assertSame('Budi S.', $this->written('SRF', 'C2'));
    }

    public function test_a_workbook_that_cannot_be_written_is_a_failure_that_can_be_tried_again(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();
        $this->actingAs($this->controller());

        // Open in Excel on somebody's machine.
        $this->onSave = fn () => Http::response(['error' => ['code' => 'resourceLocked', 'message' => 'The file is locked.']], 423);
        $this->approve($request)->assertStatus(502)->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'dikunci'));
        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame('Budi Santoso', $this->written('SRF', 'C2'));

        // The application has no right to write (it was only given the right to read).
        $this->onSave = fn () => Http::response(['error' => ['code' => 'accessDenied', 'message' => 'Access denied (token-1, secret-1).']], 403);
        $this->approve($request)->assertStatus(502)->assertJsonPath('message', fn ($message) => str_contains($message, 'Sites.Selected') && ! str_contains($message, 'secret-1'));

        // Microsoft is not reached at all.
        $this->onSave = fn () => Http::response('', 503);
        $this->approve($request)->assertStatus(502)->assertJsonPath('status', 'failed');

        // Then it works: the failed request is approved again, and is written.
        $this->onSave = null;
        $this->approve($request)->assertOk()->assertJsonPath('status', 'applied');
        $this->assertSame('Budi S.', $this->written('SRF', 'C2'));
        $this->assertNull($request->fresh()->failure);
    }

    public function test_a_workbook_that_cannot_be_read_is_a_failure_too(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        $this->bytes = 'not a workbook';
        $this->actingAs($this->controller());
        $this->approve($request)->assertStatus(502)->assertJsonPath('status', 'failed');
        $this->assertSame(0, $this->puts());

        // (A workbook that is a workbook but has no such sheet any more is a conflict: see above.)
    }

    public function test_a_change_that_cannot_be_saved_on_the_server_is_a_failure_and_nothing_is_sent(): void
    {
        // The changed workbook cannot be saved to its temporary copy, every time the first approval tries (on Windows: a
        // virus scanner holding it). (One patcher for the whole test: the controller that holds it is kept between requests.)
        $saves = 0;
        $this->app->instance(XlsxPatcher::class, new XlsxPatcher(function (ZipArchive $zip) use (&$saves): bool {
            if (++$saves > 3) {
                return $zip->close();
            }
            $zip->unchangeAll();
            $zip->close();

            return false;
        }));
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        $this->actingAs($this->controller());
        $this->approve($request)->assertStatus(502)->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'tidak bisa disimpan sementara') && str_contains($message, 'Tidak ada yang ditulis'));
        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame(3, $saves);
        $this->assertSame(0, $this->puts());
        $this->assertSame('Budi Santoso', $this->written('SRF', 'C2'));

        // Saved as it should be again: the same request is approved, and written.
        $this->approve($request)->assertOk()->assertJsonPath('status', 'applied');
        $this->assertSame(4, $saves);
        $this->assertSame('Budi S.', $this->written('SRF', 'C2'));
    }

    // ================================================================ notifications

    public function test_whoever_may_approve_is_told_of_a_request_and_whoever_asked_hears_what_became_of_it(): void
    {
        $controller = $this->controller();
        $super = $this->superAdmin();
        $this->viewer();
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();

        // Who may approve (Update on "Persetujuan Edit": the Document Controller, Super Admin) is told; not who asked (Admin
        // Radio, who may only read that page), not who may not open it.
        $this->assertEqualsCanonicalizing([$controller->id, $super->id],
            UserNotification::query()->where('kind', UserNotification::EDIT_REQUESTED)->pluck('user_id')->all());
        $notice = UserNotification::query()->where('user_id', $controller->id)->sole();
        $this->assertSame('Pengajuan perubahan menunggu persetujuan', $notice->title);
        $this->assertStringStartsWith('Admin Radio Satu mengajukan SRF C2', $notice->body);
        $this->assertStringContainsString(': "Budi Santoso" → "Budi S.".', $notice->body);
        // The link opens the page on that request, waiting.
        $this->assertSame(route('documents.edit-approvals.index', ['status' => 'pending', 'search' => $request->searchTerms()], false), $notice->url);
        $this->assertStringEndsWith(' C2', $request->searchTerms());

        // Approved, and written: whoever asked hears it.
        $this->actingAs($controller);
        $this->approve($request)->assertOk()->assertJsonPath('status', 'applied');
        $told = UserNotification::query()->where('user_id', $this->admin()->id)->sole();
        $this->assertSame([UserNotification::EDIT_DECIDED, 'Perubahan Anda sudah diterapkan'], [$told->kind, $told->title]);
        $this->assertStringEndsWith(': "Budi S." sudah ditulis ke workbook (disetujui Doc Controller Satu).', $told->body);
        $this->assertSame(route('documents.edit-approvals.index', ['status' => 'applied', 'search' => $request->searchTerms()], false), $told->url);
        // The approvers are not told twice, nor of a decision.
        $this->assertSame(1, UserNotification::query()->where('user_id', $controller->id)->count());
    }

    public function test_a_failed_or_turned_down_request_is_told_but_not_one_taken_back_by_whoever_asked(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $request = EditRequest::sole();
        $this->actingAs($this->controller());

        // Not written (the file is open in Excel): said, with the reason; it can be approved again later.
        $this->onSave = fn () => Http::response(['error' => ['code' => 'resourceLocked', 'message' => 'The file is locked.']], 423);
        $this->approve($request)->assertStatus(502);
        $failed = UserNotification::query()->where('user_id', $this->admin()->id)->sole();
        $this->assertSame('Perubahan Anda belum bisa diterapkan', $failed->title);
        $this->assertStringContainsString('dikunci', $failed->body);

        // Turned down, with a note.
        $this->patchJson(route('documents.edit-approvals.reject', $request->id), ['note' => 'Nama salah'])->assertOk();
        $rejected = UserNotification::query()->where('user_id', $this->admin()->id)->latest('id')->first();
        $this->assertSame('Pengajuan Anda ditolak', $rejected->title);
        $this->assertStringEndsWith(': ditolak oleh Doc Controller Satu: Nama salah.', $rejected->body);

        // The Document Controller asks: Super Admin is told (nobody approves their own); taken back: nobody is told of it.
        $this->ask([3, 2], 'Andi W.')->assertCreated();
        $own = EditRequest::query()->latest('id')->first();
        $this->assertSame(1, UserNotification::query()->where('user_id', $super->id)->where('body', 'like', 'Doc Controller Satu mengajukan%')->count());
        $this->assertSame(0, UserNotification::query()->where('user_id', $this->controller()->id)->where('body', 'like', 'Doc Controller Satu mengajukan%')->count());
        $before = UserNotification::query()->count();
        $this->patchJson(route('documents.edit-approvals.reject', $own->id))->assertOk();
        $this->assertSame($before, UserNotification::query()->count());
    }

    // ================================================================ the list

    public function test_the_list_counts_and_narrows_the_requests(): void
    {
        $this->actingAs($this->admin());
        $this->ask([2, 2], 'Budi S.')->assertCreated();
        $this->ask([3, 2], 'Andi [W]_%')->assertCreated();
        $this->ask([2, 5], 'SN-1')->assertCreated();

        $this->actingAs($this->controller());
        $this->approve(EditRequest::where('cell', 'F2')->sole())->assertOk();
        $this->patchJson(route('documents.edit-approvals.reject', EditRequest::where('cell', 'C3')->sole()->id), ['note' => 'tidak perlu'])->assertOk();

        $list = $this->getJson(route('documents.edit-approvals.list'))->assertOk();
        $list->assertJsonPath('counts.pending', 1)->assertJsonPath('counts.applied', 1)->assertJsonPath('counts.rejected', 1)
            ->assertJsonPath('counts.conflict', 0)->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', EditRequest::where('cell', 'F2')->value('id'));
        $this->assertSame(
            ['Menunggu'],
            $this->getJson(route('documents.edit-approvals.list', ['status' => 'pending']))->assertJsonPath('meta.total', 1)->json('data.*.status_label')
        );
        // The counts are of what the words match, whatever status is shown.
        $this->getJson(route('documents.edit-approvals.list', ['status' => 'applied', 'search' => 'SN-1']))
            ->assertJsonPath('meta.total', 1)->assertJsonPath('counts.applied', 1)->assertJsonPath('counts.pending', 0);
        // The words of a search are not patterns: a bracket, a percent sign or an underscore is what it is.
        $this->getJson(route('documents.edit-approvals.list', ['search' => '[W]_%']))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.cell', 'C3');
        $this->getJson(route('documents.edit-approvals.list', ['search' => '%']))->assertJsonPath('meta.total', 1);
        $this->getJson(route('documents.edit-approvals.list', ['search' => 'Admin Radio Satu tidak']))->assertJsonPath('meta.total', 0);

        $first = $this->getJson(route('documents.edit-approvals.list', ['status' => 'rejected']))->json('data.0');
        $this->assertSame(
            ['ICT Masterlist', 'SRF', 'C3', 'Requested By', 'Andi Wijaya', 'Andi [W]_%', 'Ditolak', 'tidak perlu', '/documents/ict-masterlist?sheet=SRF&line=3'],
            [$first['workbook'], $first['sheet'], $first['cell'], $first['column'], $first['old'], $first['new'], $first['status_label'], $first['note'], $first['open']]
        );

        // The page itself, and a reader without the menu.
        $this->get(route('documents.edit-approvals.index'))->assertOk()->assertSee('Persetujuan Edit');
        $this->actingAs($this->viewer());
        $this->get(route('documents.edit-approvals.index'))->assertForbidden();
        $this->getJson(route('documents.edit-approvals.list'))->assertForbidden();
    }

    // ================================================================ the menu

    public function test_the_menu_and_the_roles_are_given_as_agreed(): void
    {
        $read = CheckMenuPermission::PERMISSION_IDS['Read'];
        $update = CheckMenuPermission::PERMISSION_IDS['Update'];
        $role = fn (string $name) => (int) DB::table('roles')->where('name', $name)->value('id');
        $grants = fn (string $route) => DB::table('menu_role_permission')
            ->where('menu_id', DB::table('menu')->where('route', $route)->value('id'))->orderBy('role_id')->orderBy('permission_id')->get()
            ->map(fn ($grant) => [(int) $grant->role_id, (int) $grant->permission_id])->all();
        [$admin, $controller] = [$role('Admin Radio'), $role('Document Controller')];
        $this->assertGreaterThan(0, $controller);

        // The page of the approvals: in the section "Dokumen" below the masterlist, the others below it.
        $menu = DB::table('menu')->where('route', EditApprovalsMenuSeeder::ROUTE)->sole();
        // (Counted from the last entry of the section "Radio", Import Excel: the pages that came with the design refresh moved
        // the section down, ServiceTicketTest.)
        $import = (int) DB::table('menu')->where('route', 'radio-management.import.index')->value('order');
        $this->assertSame(['Persetujuan Edit', 'Dokumen', null, $import + 3], [$menu->title, $menu->section, $menu->parent_id, (int) $menu->order]);
        // (Since 2026-10-17 "Audit Log" and "API Tokens" come right after "User Management", before the hidden "Approval
        // Management": AuditLogTest, ApiTokensPageTest.)
        $this->assertSame(
            ['COF Scan' => $import + 1, 'ICT Masterlist' => $import + 2, 'Persetujuan Edit' => $import + 3, 'User Management' => $import + 4,
                'Audit Log' => $import + 5, 'API Tokens' => $import + 6, 'Approval Management' => $import + 7],
            DB::table('menu')->whereNull('parent_id')->whereIn('title', ['COF Scan', 'ICT Masterlist', 'Persetujuan Edit', 'User Management', 'Audit Log', 'API Tokens', 'Approval Management'])
                ->orderBy('order')->pluck('order', 'title')->map(fn ($place) => (int) $place)->all()
        );
        $this->assertSame('/documents/edit-approvals', route(EditApprovalsMenuSeeder::ROUTE, [], false));

        // Who may ask (Update on the masterlist), who sees the requests (Read), who decides (Update on the approvals): and not Viewer Radio.
        $this->assertSame([[1, $read], [$admin, $read], [$admin, $update], [$controller, $read], [$controller, $update]], $grants(IctMasterlistMenuSeeder::ROUTE));
        $this->assertSame([[1, $read], [$admin, $read], [$controller, $read], [$controller, $update]], $grants(EditApprovalsMenuSeeder::ROUTE));
        $this->assertContains([$controller, $read], $grants('documents.cof-scan.index'));
        $this->assertNotContains([$controller, $update], $grants('documents.cof-scan.index'));
        // The Document Controller can sign in: the dashboard is theirs, read only.
        $this->assertSame([[$controller, $read]], array_values(array_filter($grants('dashboard.index'), fn ($grant) => $grant[0] === $controller)));

        // Running it again changes nothing.
        $this->seed(DocumentEditAccessSeeder::class);
        $this->seed(EditApprovalsMenuSeeder::class);
        $this->assertSame([[1, $read], [$admin, $read], [$admin, $update], [$controller, $read], [$controller, $update]], $grants(IctMasterlistMenuSeeder::ROUTE));
        $this->assertSame(1, DB::table('roles')->where('name', 'Document Controller')->count());
    }

    // ================================================================ writing a row straight away (6.6, K13h)

    /** Writes cells of a row as the dialog "Edit baris" does: the columns by their place in the table. */
    private function writeRow(int $line, array $values, int $sheet = 0): TestResponse
    {
        return $this->patchJson(route('documents.ict-masterlist.write-row'), ['sheet' => $sheet, 'line' => $line, 'values' => $values]);
    }

    public function test_who_may_approve_changes_writes_a_row_straight_away_and_nobody_else(): void
    {
        // The Document Controller and Super Admin may ask and approve: the page offers them "Edit baris".
        foreach ([$this->controller(), $this->superAdmin()] as $user) {
            $this->actingAs($user->fresh());
            $this->getJson(route('documents.ict-masterlist.rows'))->assertOk()->assertJsonPath('direct', true);
        }
        // Admin Radio may only ask: not offered, and refused.
        $this->actingAs($this->admin()->fresh());
        $this->getJson(route('documents.ict-masterlist.rows'))->assertOk()->assertJsonPath('direct', false);
        $this->writeRow(2, [2 => 'Budi S.'])->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak berwenang mengubah workbook ini langsung. Ajukan perubahan per sel.');
        // Nor an API client, whatever its role.
        $client = $this->makeUser('client@api-client.invalid', 1, 'Klien API');
        $client->forceFill(['api_client' => true])->save();
        $this->actingAs($client->fresh());
        $this->getJson(route('documents.ict-masterlist.rows'))->assertOk()->assertJsonPath('direct', false);
        $this->writeRow(2, [2 => 'Budi S.'])->assertForbidden();
        $this->assertSame(0, $this->puts());
        $this->assertSame(0, EditRequest::count());
    }

    public function test_a_row_is_read_once_checked_and_put_back_once_with_its_version(): void
    {
        $controller = $this->controller();
        $this->actingAs($controller);
        $version = $this->version();

        // Three cells: a text, a number (the cell held one), a text of digits (the cell held text).
        $this->writeRow(2, [2 => 'Budi S.', 3 => '5', 5 => '000124', 0 => 'ICT/SRF/1'])->assertOk()
            ->assertJsonPath('message', 'Baris 2 tersimpan ke SharePoint (3 sel).')
            ->assertJsonPath('cells', ['C2', 'D2', 'F2']);
        $this->assertSame(1, $this->puts());
        $this->assertSame($version, $this->saved[0]['ifMatch']);
        $this->assertSame(['Budi S.', '5', '000124'], [$this->written('SRF', 'C2'), $this->written('SRF', 'D2'), $this->written('SRF', 'F2')]);

        // Kept as requests applied by the one who made them, marked "direct", each in the audit trail.
        $made = EditRequest::query()->orderBy('id')->get();
        $this->assertSame([['C2', 'applied', true], ['D2', 'applied', true], ['F2', 'applied', true]],
            $made->map(fn (EditRequest $one) => [$one->cell, $one->status, $one->direct])->all());
        $this->assertSame([[$controller->id, $controller->id]], $made->map(fn (EditRequest $one) => [$one->requested_by, (int) $one->decided_by])->unique()->values()->all());
        $audit = AuditLog::query()->where('action', 'document.edited_directly')->orderBy('id')->get();
        $this->assertCount(3, $audit);
        $this->assertSame([['Value' => 'Budi Santoso'], ['Value' => 'Budi S.', 'Column' => 'Requested By']], [$audit[0]->old_values, $audit[0]->new_values]);
        // The page of the approvals shows them as written straight away.
        $this->getJson(route('documents.edit-approvals.list', ['status' => 'applied']))->assertOk()->assertJsonPath('data.0.direct', true);

        // Nothing changed, a formula, a value that is no number for a number: refused, nothing written.
        $this->writeRow(2, [2 => 'Budi S.'])->assertStatus(422)->assertJsonPath('message', 'Tidak ada isi yang berubah.');
        $this->writeRow(2, [6 => '7'])->assertStatus(422)->assertJsonPath('message', 'Kolom "Check" berisi rumus. Ubah di Excel.');
        $this->writeRow(2, [3 => 'lima'])->assertStatus(422)->assertJsonPath('message', fn ($message) => str_starts_with($message, 'Kolom "Qty": Kolom ini berisi angka'));
        $this->writeRow(99, [2 => 'x'])->assertStatus(422)->assertJsonPath('message', 'Baris itu tidak ada lagi di sheet ini. Muat ulang halaman.');
        $this->writeRow(2, ['x' => 'y'])->assertStatus(422);
        $this->assertSame(1, $this->puts());
    }

    public function test_nothing_is_written_when_the_row_changed_or_sharepoint_refuses_the_file(): void
    {
        $this->actingAs($this->controller());
        $this->getJson(route('documents.ict-masterlist.rows'))->assertOk();

        // Changed in SharePoint since the page was loaded: a conflict, nothing written.
        $this->savedByOther('SRF', 'C2', 'Orang Lain');
        $this->writeRow(2, [2 => 'Budi S.', 3 => '5'])->assertStatus(409)
            ->assertJsonPath('message', 'Baris 2 sudah berubah di SharePoint sejak halaman dimuat (sel C2). Tidak ada yang ditulis; muat ulang halaman, lalu ulangi.');
        $this->assertSame(0, $this->puts());

        // Saved by somebody else between reading and putting back (412): nothing kept.
        $this->onSave = fn () => Http::response(['error' => ['code' => 'preconditionFailed', 'message' => 'The ETag does not match.']], 412);
        $this->writeRow(3, [2 => 'Andi W.'])->assertStatus(412)
            ->assertJsonPath('message', 'Workbook disimpan orang lain di SharePoint sejak dibaca. Tidak ada yang ditulis; muat ulang halaman, lalu ulangi.');
        // Open in Excel (423).
        $this->onSave = fn () => Http::response(['error' => ['code' => 'resourceLocked', 'message' => 'The file is locked.']], 423);
        $this->writeRow(3, [2 => 'Andi W.'])->assertStatus(423)
            ->assertJsonPath('message', 'Workbook sedang dikunci karena dibuka orang lain di Excel. Tidak ada yang ditulis; coba lagi beberapa saat lagi.');
        $this->assertSame('Andi Wijaya', $this->written('SRF', 'C3'));
        $this->assertSame(0, EditRequest::count());
        $this->assertSame(0, AuditLog::query()->where('action', 'document.edited_directly')->count());

        // A cell that waits for a decision is decided first.
        $this->onSave = null;
        $this->actingAs($this->admin());
        $this->ask([3, 2], 'Andi Baru')->assertCreated();
        $this->actingAs($this->controller());
        $this->writeRow(3, [2 => 'Andi W.', 3 => '11'])->assertStatus(409)
            ->assertJsonPath('message', 'Sel C3 punya pengajuan yang menunggu keputusan (diajukan oleh Admin Radio Satu). Putuskan dulu di halaman Persetujuan Edit.');
        $this->writeRow(3, [3 => '11'])->assertOk()->assertJsonPath('cells', ['D3']);
    }

    public function test_before_its_migration_has_run_nobody_writes_a_row_straight_away(): void
    {
        Schema::table('sharepoint_edit_requests', fn ($table) => $table->dropColumn('direct'));
        Cache::forget('sharepoint-edit-direct-column');
        $this->actingAs($this->controller());

        $this->getJson(route('documents.ict-masterlist.rows'))->assertOk()->assertJsonPath('direct', false);
        $this->writeRow(2, [2 => 'Budi S.'])->assertForbidden();
        // Asking for a change of a cell goes on as before.
        $this->ask([2, 2], 'Budi S.')->assertCreated();
    }

    public function test_the_migration_adds_it_to_a_database_in_use_and_takes_it_away_again(): void
    {
        // The database as it was when this migration was new: without the later "Stock Opname", "Perlu Perhatian", "Servis &
        // Perbaikan", "API Tokens" and "Audit Log" (rolled back, the latest first).
        (require database_path('migrations/2026_10_20_150000_add_opname_menu.php'))->down();
        (require database_path('migrations/2026_10_20_130000_add_attention_menu.php'))->down();
        (require database_path('migrations/2026_10_20_110000_add_service_menu.php'))->down();
        (require database_path('migrations/2026_10_17_150000_add_api_tokens_menu.php'))->down();
        (require database_path('migrations/2026_10_17_110000_add_audit_log_menu.php'))->down();
        $migration = require database_path('migrations/2026_10_12_110000_add_edit_approvals_menu.php');
        $update = CheckMenuPermission::PERMISSION_IDS['Update'];
        $order = fn () => DB::table('menu')->whereNull('parent_id')->whereIn('title', ['COF Scan', 'ICT Masterlist', 'Persetujuan Edit', 'User Management', 'Approval Management'])
            ->orderBy('order')->pluck('order', 'title')->map(fn ($place) => (int) $place)->all();

        $migration->down();
        $this->assertSame(['COF Scan' => 7, 'ICT Masterlist' => 8, 'User Management' => 9, 'Approval Management' => 10], $order());
        $this->assertFalse(DB::table('menu')->where('route', EditApprovalsMenuSeeder::ROUTE)->exists());
        $this->assertFalse(DB::table('roles')->where('name', 'Document Controller')->exists());
        $ict = DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->value('id');
        $this->assertFalse(DB::table('menu_role_permission')->where('menu_id', $ict)->where('permission_id', $update)->exists());

        $migration->up();
        $this->assertSame(['COF Scan' => 7, 'ICT Masterlist' => 8, 'Persetujuan Edit' => 9, 'User Management' => 10, 'Approval Management' => 11], $order());
        $this->assertSame(2, DB::table('menu_role_permission')->where('menu_id', $ict)->where('permission_id', $update)->count());

        // A role with somebody in it stays when the migration is rolled back; what it was given on the page of the approvals goes.
        $controller = $this->controller();
        $migration->down();
        $this->assertTrue(DB::table('roles')->where('name', 'Document Controller')->exists());
        $this->assertSame(0, DB::table('menu_role_permission')->where('menu_id', $ict)->where('permission_id', $update)->count());
        $this->assertTrue(User::whereKey($controller->id)->exists());
    }
}
