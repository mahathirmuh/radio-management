<?php

namespace Tests\Feature\Api;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Api\Http\Middleware\PersonOnly;
use Modules\Api\Providers\ApiServiceProvider;
use Modules\SharePointDocs\Models\EditRequest;
use Tests\Concerns\FakesSharePoint;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\MakesWorkbooks;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * COF Scan, ICT Masterlist and "Persetujuan Edit" through the API: what their pages show, read the same way (FolderView,
 * WorkbookView, CellEdits), with the permissions of their menus. Microsoft is played by Http::fake() (FakesSharePoint).
 */
class ApiSharePointTest extends TestCase
{
    use FakesSharePoint;
    use MakesUsers;
    use MakesWorkbooks;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeSharePoint();
    }

    private function adminRadio(bool $write = true): string
    {
        return $this->tokenFor($this->makeUser(uniqid('admin') . '@test.local', 'Admin Radio', 'Admin Radio Satu'), $write);
    }

    private function controller(bool $write = true, string $name = 'Doc Controller'): string
    {
        return $this->tokenFor($this->makeUser(uniqid('doc') . '@test.local', 'Document Controller', $name), $write, name: 'Persetujuan');
    }

    // ================================================================ the folder

    public function test_the_folder_lists_its_files_with_what_the_masterlist_says(): void
    {
        $token = $this->adminRadio(write: false);

        $all = $this->api('GET', '/api/v1/documents/cof-scan', token: $token)->assertOk()->json();
        // A folder first, then the files newest first.
        $this->assertSame(['Lama', 'ICTCOF1 - Budi Santoso.pdf', 'ICTCOF2 - Andi  Wijaya.pdf', 'ICTCOF9 - Tanpa Baris.pdf'], array_column($all['data'], 'name'));
        [$folder, $first, $second, $third] = $all['data'];
        $this->assertSame(['folder', 'Lama', null, false], [$folder['type'], $folder['path'], $folder['size'], $folder['downloadable']]);
        $this->assertSame(['file', 'PDF1', 'ICTCOF1', 'Budi Santoso', 'pdf', 1000, true, null], [
            $first['type'], $first['id'], $first['code'], $first['label'], $first['extension'], $first['size'], $first['downloadable'], $first['path'],
        ]);
        $this->assertSame('2026-01-03T08:00:00+08:00', $first['modified_at']);
        $this->assertStringEndsWith('?web=1', $first['web_url']);
        // Too large to be given out.
        $this->assertFalse($third['downloadable']);

        // What the sheet COF says of each: its own row, a row with its number but another name, no row.
        $this->assertSame(['status' => 'linked', 'lines' => [2], 'total' => 1, 'note' => 'Menyusul'], $first['masterlist']);
        $this->assertSame(['differs', [3]], [$second['masterlist']['status'], $second['masterlist']['lines']]);
        $this->assertSame(['unlinked', []], [$third['masterlist']['status'], $third['masterlist']['lines']]);
        $this->assertSame(['ICT Masterlist', 'COF', false], [$all['masterlist']['workbook'], $all['masterlist']['sheet'], $all['masterlist']['stale']]);
        $this->assertSame([3, 1, 2000 + 50 * 1024 * 1024, false, null], [
            $all['folder']['files'], $all['folder']['folders'], $all['folder']['size'], $all['folder']['stale'], $all['folder']['code'],
        ]);

        // Searched, by a document number, sorted, a page at a time.
        $this->assertSame(['ICTCOF1 - Budi Santoso.pdf'], array_column($this->api('GET', '/api/v1/documents/cof-scan?search=budi', token: $token)->json('data'), 'name'));
        $this->assertSame(['ICTCOF2 - Andi  Wijaya.pdf'], array_column($this->api('GET', '/api/v1/documents/cof-scan?code=ICT/COF/2', token: $token)->json('data'), 'name'));
        $this->assertSame('ICTCOF9 - Tanpa Baris.pdf', $this->api('GET', '/api/v1/documents/cof-scan?sort=masterlist&direction=asc', token: $token)->json('data.1.name'));
        $paged = $this->api('GET', '/api/v1/documents/cof-scan?sort=name&direction=desc&per_page=2&page=2', token: $token)->json();
        $this->assertSame(['ICTCOF2 - Andi  Wijaya.pdf', 'ICTCOF1 - Budi Santoso.pdf'], array_column($paged['data'], 'name'));
        $this->assertSame([2, 2, 4], [$paged['meta']['current_page'], $paged['meta']['last_page'], $paged['meta']['total']]);
        $this->api('GET', '/api/v1/documents/cof-scan?sort=size2&path=../x', token: $token)->assertStatus(422)->assertJsonValidationErrors(['sort']);
        $this->api('GET', '/api/v1/documents/cof-scan?path=../x', token: $token)->assertStatus(422)->assertJsonValidationErrors(['path']);
    }

    public function test_the_folder_follows_the_menus_of_both_pages(): void
    {
        // No COF Scan: no folder.
        $this->api('GET', '/api/v1/documents/cof-scan', token: $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio')))
            ->assertForbidden()->assertJsonPath('message', 'The role of this account has no access to "COF Scan".');

        // COF Scan without the workbook: the files, without what the sheet says of them.
        $onlyFolder = $this->tokenFor($this->makeUser('arsip@test.local', $this->roleWith(['documents.cof-scan' => ['Read']])));
        $answer = $this->api('GET', '/api/v1/documents/cof-scan', token: $onlyFolder)->assertOk()->json();
        $this->assertNull($answer['masterlist']);
        $this->assertArrayNotHasKey('masterlist', $answer['data'][1]);
    }

    public function test_a_file_is_given_out_as_it_is_and_only_from_the_list(): void
    {
        $token = $this->adminRadio(write: false);

        $file = $this->api('GET', '/api/v1/documents/cof-scan/files/PDF1', token: $token)->assertOk();
        $this->assertSame(self::SP_PDF, $file->getContent());
        $this->assertSame('application/pdf', $file->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="ICTCOF1 - Budi Santoso.pdf"', $file->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $file->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $file->headers->get('X-Content-Type-Options'));

        // Not a file of the list, a folder, one that is too large.
        $this->api('GET', '/api/v1/documents/cof-scan/files/NOPE', token: $token)->assertNotFound()->assertJsonPath('reason', 'not_found');
        $this->api('GET', '/api/v1/documents/cof-scan/files/DIR1', token: $token)->assertNotFound();
        $this->api('GET', '/api/v1/documents/cof-scan/files/PDF9', token: $token)->assertStatus(413)->assertJsonPath('reason', 'too_large');
        // Not for who may not open the folder.
        $this->api('GET', '/api/v1/documents/cof-scan/files/PDF1', token: $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio')))->assertForbidden();
    }

    // ================================================================ the workbook

    public function test_the_workbook_and_its_sheets(): void
    {
        $book = $this->api('GET', '/api/v1/documents/ict-masterlist', token: $this->adminRadio())->assertOk()->json('data');
        $this->assertSame(['ICT Masterlist', 'Daftar Induk.xlsx', false, true], [$book['title'], $book['name'], $book['stale'], $book['editable']]);
        $this->assertSame([
            ['index' => 0, 'name' => 'SRF', 'columns' => ['SRF No', 'Date', 'Requested By', 'Qty', 'Check'], 'rows' => 2, 'truncated' => false, 'scan_check' => false],
            ['index' => 1, 'name' => 'COF', 'columns' => ['No', 'COF No', 'Catatan', 'Tautan'], 'rows' => 3, 'truncated' => false, 'scan_check' => true],
        ], $book['sheets']);

        // A token that only reads asks for no change.
        $this->assertFalse($this->api('GET', '/api/v1/documents/ict-masterlist', token: $this->adminRadio(write: false))->json('data.editable'));
        $this->api('GET', '/api/v1/documents/ict-masterlist', token: $this->tokenFor($this->makeUser('viewer@test.local', 'Viewer Radio')))->assertForbidden();
    }

    public function test_the_rows_of_a_sheet_as_the_page_shows_them(): void
    {
        $token = $this->adminRadio(write: false);

        $cof = $this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=COF', token: $token)->assertOk()->json();
        // The last row first, with "File scan" as the last column and what it says of each row.
        $this->assertSame([4, 3, 2], array_column($cof['data'], 'line'));
        $this->assertSame(['No', 'COF No', 'Catatan', 'Tautan', 'File scan'], $cof['sheet']['columns']);
        $this->assertSame(['missing', 'differs', 'ok'], array_map(fn ($row) => $row['scan']['status'], $cof['data']));
        $this->assertSame(['Belum ada', 'Nama beda', 'Ada'], array_map(fn ($row) => $row['cells'][4], $cof['data']));
        $this->assertSame(['status' => 'ok', 'label' => 'Ada', 'file_code' => 'ICTCOF1'], $cof['data'][2]['scan']);
        $this->assertSame(['total' => 3, 'ok' => 1, 'missing' => 1, 'differs' => 1, 'below' => 0], $cof['sheet']['scan']['counts']);
        $this->assertSame([1, 'COF', 4, null, 'desc', 0], [$cof['sheet']['index'], $cof['sheet']['name'], $cof['sheet']['scan']['column'], $cof['sheet']['sort_column'], $cof['sheet']['direction'], $cof['sheet']['editable_columns']]);
        $this->assertArrayNotHasKey('locked', $cof['data'][0]);

        // One kind of row, the rows of Excel asked for, a search (counted on every sheet), a column to sort by.
        $this->assertSame([4], array_column($this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=COF&status=missing', token: $token)->json('data'), 'line'));
        $this->assertSame([2], array_column($this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=1&line=2', token: $token)->json('data'), 'line'));
        $found = $this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=0&search=andi', token: $token)->json();
        $this->assertSame([3], array_column($found['data'], 'line'));
        $this->assertSame([1, 1], array_column($found['sheets'], 'matches'));
        $this->assertSame(['ICT/SRF/1', 'ICT/SRF/2'], array_map(fn ($row) => $row['cells'][0], $this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=SRF&sort=3&direction=asc', token: $token)->json('data')));
        $this->api('GET', '/api/v1/documents/ict-masterlist/rows?status=gone&sort=c1', token: $token)->assertStatus(422)->assertJsonValidationErrors(['status', 'sort']);

        // For who may ask for a change: the cells with a formula.
        $srf = $this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=SRF&direction=asc', token: $this->adminRadio())->json();
        $this->assertSame([4], $srf['data'][0]['locked']);
        $this->assertSame(5, $srf['sheet']['editable_columns']);
    }

    // ================================================================ changing a cell

    public function test_a_change_is_asked_for_and_written_only_when_a_person_approves_it(): void
    {
        $asker = $this->adminRadio();
        $made = $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 3, 'column' => 2, 'value' => 'Andi W.'], $asker)
            ->assertCreated()->json('data');
        $this->assertSame(['SRF', 'C3', 3, 'Requested By', 'ICT/SRF/2', 'Andi Wijaya', 'Andi W.', 'pending', true, false], [
            $made['sheet'], $made['cell'], $made['line'], $made['column'], $made['row_key'], $made['old'], $made['new'], $made['status'], $made['mine'], $made['can_approve'],
        ]);
        $this->assertSame([], $this->spSaved, 'nothing is written when it is asked for');

        // One request per cell at a time; a formula is not changed; the value it holds is no change.
        $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 3, 'column' => 2, 'value' => 'Lain'], $asker)->assertStatus(409);
        $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 2, 'column' => 4, 'value' => '9'], $asker)->assertStatus(422);
        $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 2, 'column' => 2, 'value' => 'Budi Santoso'], $asker)->assertStatus(422);
        $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 3], $asker)->assertStatus(422)->assertJsonValidationErrors(['column']);
        // A role without Update on the workbook, a token that reads.
        $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 2, 'column' => 2, 'value' => 'X'], $this->tokenFor($this->makeUser('arsip@test.local', $this->roleWith(['documents.ict-masterlist' => ['Read']]))))->assertForbidden();
        $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 0, 'line' => 2, 'column' => 2, 'value' => 'X'], $this->adminRadio(write: false))->assertForbidden();

        // The rows say which cell waits.
        $this->assertSame(['2' => 'Andi W.'], $this->api('GET', '/api/v1/documents/ict-masterlist/rows?sheet=SRF&line=3', token: $asker)->json('data.0.waiting'));

        // A person who may approve sees it, and approves it: now it is written.
        $approver = $this->controller(name: 'Dewi Kontrol');
        $list = $this->api('GET', '/api/v1/documents/edit-requests', token: $approver)->assertOk()->json();
        $this->assertTrue($list['may_decide']);
        $this->assertSame([$made['id'], true, false], [$list['data'][0]['id'], $list['data'][0]['can_approve'], $list['data'][0]['mine']]);
        $this->assertSame(1, $list['counts']['pending']);

        $approved = $this->api('POST', "/api/v1/documents/edit-requests/{$made['id']}/approve", token: $approver)->assertOk()->json();
        $this->assertSame(['applied', 'Dewi Kontrol'], [$approved['data']['status'], $approved['data']['decided_by']['name']]);
        $this->assertSame('Andi W.', $this->spWritten('SRF', 'C3'));
        $this->assertCount(1, $this->spSaved);
        $this->api('POST', "/api/v1/documents/edit-requests/{$made['id']}/approve", token: $approver)->assertStatus(409);

        // In the audit trail once each, with the token that did it.
        $asked = AuditLog::where('action', AuditLogger::EDIT_REQUESTED)->sole();
        $done = AuditLog::where('action', AuditLogger::EDIT_APPROVED)->sole();
        $this->assertSame(['Value' => 'Andi W.', 'Column' => 'Requested By'], $asked->new_values);
        $this->assertSame(['ICT Masterlist · SRF!C3', 'Diterapkan'], [$done->entity_label, $done->new_values['Result']]);
        $this->assertMatchesRegularExpression('/^API · Test token \(#\d+\)$/', (string) $asked->via);
        $this->assertMatchesRegularExpression('/^API · Persetujuan \(#\d+\)$/', (string) $done->via);
    }

    public function test_approving_and_turning_down_are_a_persons_decision(): void
    {
        $id = $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 1, 'line' => 4, 'column' => 2, 'value' => 'Belum discan'], $this->adminRadio())
            ->assertCreated()->json('data.id');

        // The token of an application, even with the role that approves: refused.
        $client = $this->tokenFor($this->apiClient('Sistem Arsip', 'Document Controller'));
        $this->api('POST', "/api/v1/documents/edit-requests/{$id}/approve", token: $client)->assertForbidden()->assertJsonPath('message', PersonOnly::REFUSED);
        $this->api('POST', "/api/v1/documents/edit-requests/{$id}/reject", token: $client)->assertForbidden();
        $this->assertFalse($this->api('GET', '/api/v1/documents/edit-requests', token: $client)->assertOk()->json('may_decide'));
        // A person's token that only reads; a role that may only read the requests.
        $this->api('POST', "/api/v1/documents/edit-requests/{$id}/approve", token: $this->controller(write: false))->assertForbidden()->assertJsonPath('message', 'This token may only read.');
        $this->api('POST', "/api/v1/documents/edit-requests/{$id}/approve", token: $this->adminRadio())->assertForbidden();

        // Nobody approves their own request.
        $super = $this->tokenFor($this->superAdmin());
        $own = $this->api('POST', '/api/v1/documents/ict-masterlist/edit-requests', ['sheet' => 1, 'line' => 2, 'column' => 2, 'value' => 'Sudah'], $super)->json('data.id');
        $this->api('POST', "/api/v1/documents/edit-requests/{$own}/approve", token: $super)->assertForbidden()
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'sendiri'));

        // Turned down by a person, with the reason.
        $rejected = $this->api('POST', "/api/v1/documents/edit-requests/{$id}/reject", ['note' => 'Belum perlu'], $this->controller())->assertOk()->json('data');
        $this->assertSame(['rejected', 'Belum perlu'], [$rejected['status'], $rejected['note']]);
        $this->assertSame([], $this->spSaved);
        $this->api('GET', "/api/v1/documents/edit-requests/{$id}", token: $client)->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->api('GET', '/api/v1/documents/edit-requests?status=rejected', token: $client)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', '/api/v1/documents/edit-requests/999999', token: $client)->assertNotFound();
    }

    // ================================================================ when SharePoint is not there

    public function test_sharepoint_that_cannot_be_reached_is_said_with_its_reason(): void
    {
        $token = $this->adminRadio(write: false);
        $this->spDown = true;
        Cache::flush();

        $this->api('GET', '/api/v1/documents/cof-scan', token: $token)->assertStatus(503)->assertJsonPath('reason', 'unavailable')
            ->assertJsonPath('message', 'SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        $this->api('GET', '/api/v1/documents/ict-masterlist/rows', token: $token)->assertStatus(503)->assertJsonPath('reason', 'unavailable');

        // Not set up at all.
        config(['sharepoint-docs.workbooks.ict-masterlist.url' => null]);
        $this->api('GET', '/api/v1/documents/ict-masterlist', token: $token)->assertStatus(503)->assertJsonPath('reason', 'not_configured');
    }

    public function test_what_is_read_from_sharepoint_has_its_own_limit(): void
    {
        $token = $this->controller(write: false);
        for ($i = 1; $i <= ApiServiceProvider::SHAREPOINT_PER_TOKEN; $i++) {
            $this->api('GET', '/api/v1/documents/edit-requests', token: $token)->assertOk();
        }
        $this->api('GET', '/api/v1/documents/edit-requests', token: $token)->assertStatus(429);
        // The rest of the API goes on.
        $this->api('GET', '/api/v1/me', token: $token)->assertOk();
    }
}
