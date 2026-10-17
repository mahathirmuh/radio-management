<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request as GraphRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Modules\SharePointDocs\Services\XlsxPatcher;

/**
 * A pretend SharePoint (Http::fake()) with one folder of scans ("COF Scan") and one workbook ("ICT Masterlist") whose sheet
 * COF lists the scans, as the real ones are tied together. What is put back into the workbook is kept, so that a test can
 * read what an approval wrote. Every name and address is made up. Uses MakesWorkbooks.
 */
trait FakesSharePoint
{
    protected const SP_BOOK_ADDRESS = 'https://contoso.sharepoint.com/:x:/r/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B11111111-2222-3333-4444-555555555555%7D&file=Daftar%20Induk.xlsx&action=default';

    protected const SP_FOLDER_ADDRESS = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Forms/AllItems.aspx?id=%2Fsites%2FTeam%2FShared%20Documents%2FScans&viewid=1';

    protected const SP_FOLDER_URL = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Scans';

    protected const SP_DOWNLOAD = 'https://contoso.sharepoint.com/sites/Team/_layouts/15/download.aspx';

    protected const SP_PDF = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    /** What the pretend SharePoint holds: the bytes of the workbook, and its version (another one with every save). */
    protected string $spBook = '';

    protected int $spSaves = 1;

    /** @var array<int, string> everything that was put into the workbook */
    protected array $spSaved = [];

    /** @var bool whether Microsoft cannot be reached */
    protected bool $spDown = false;

    private ?float $spBegan = null;

    protected function fakeSharePoint(): void
    {
        // The tests are not to count the age of their process in the time a request may take (see ScanLinks::begin()).
        $this->spBegan = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 3600;
        $this->beforeApplicationDestroyed(function () {
            if ($this->spBegan === null) {
                unset($_SERVER['REQUEST_TIME_FLOAT']);
            } else {
                $_SERVER['REQUEST_TIME_FLOAT'] = $this->spBegan;
            }
        });

        Sleep::fake();
        config([
            'logging.default' => 'null',
            'services.azure.tenant' => 'tenant-1',
            'services.azure.client_id' => 'client-1',
            'services.azure.client_secret' => 'secret-1',
            'sharepoint-docs.folders.cof-scan.url' => self::SP_FOLDER_ADDRESS,
            'sharepoint-docs.workbooks.ict-masterlist.url' => self::SP_BOOK_ADDRESS,
            'sharepoint-docs.workbooks.ict-masterlist.sheets' => null,
            'sharepoint-docs.workbooks.ict-masterlist.skip_unused_rows' => false,
            'sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['folder' => 'cof-scan', 'link' => 'Tautan', 'code' => 'COF No', 'note' => 'Catatan']],
            'sharepoint-docs.workbooks.ict-masterlist.edit.enabled' => true,
            'sharepoint-docs.workbooks.ict-masterlist.edit.anchors' => ['COF' => 'COF No'],
            'sharepoint-docs.cache_minutes' => 10,
            'sharepoint-docs.time_limit' => 20,
            'sharepoint-docs.preview_time_limit' => 25,
            'app.timezone' => 'Asia/Makassar',
        ]);
        $this->spBook = self::xlsx(self::spSheets());

        Http::fake(function (GraphRequest $request) {
            $url = $request->url();
            $drive = 'https://graph.microsoft.com/v1.0/drives/';
            if ($this->spDown) {
                return Http::response('down', 503);
            }

            return match (true) {
                str_starts_with($url, 'https://login.microsoftonline.com/') => Http::response(['token_type' => 'Bearer', 'expires_in' => 3599, 'access_token' => 'token-1']),
                str_starts_with($url, 'https://graph.microsoft.com/v1.0/shares/') => Http::response($this->spIsTheWorkbook($url) ? $this->spBookItem() : [
                    'id' => 'ROOT1', 'name' => 'Scans', 'webUrl' => self::SP_FOLDER_URL, 'folder' => ['childCount' => 4], 'parentReference' => ['driveId' => 'b!drive-1'],
                ]),
                str_starts_with($url, $drive) && $request->method() === 'PUT' => $this->spSave($request),
                str_starts_with($url, $drive) && str_ends_with((string) strtok($url, '?'), '/content') => Http::response('', 302, [
                    'Location' => self::SP_DOWNLOAD . '?UniqueId=' . (preg_match('#/items/([^/]+)/content#', $url, $id) === 1 ? $id[1] : '') . '&tempauth=one-time',
                ]),
                str_starts_with($url, $drive) => Http::response(['value' => self::spFiles()]),
                str_starts_with($url, self::SP_DOWNLOAD) => Http::response(str_contains($url, 'UniqueId=BOOK1&') ? $this->spBook : self::SP_PDF),
                default => Http::response('not Microsoft', 500),
            };
        });
    }

    /**
     * The workbook. COF row 2: its file is in the folder; row 3: the file is named otherwise; row 4: no file. SRF: a sheet that is
     * not tied to a folder, with a date, a number and a formula.
     */
    protected static function spSheets(): array
    {
        return [
            'SRF' => [
                ['SRF No', 'Date', 'Requested By', 'Qty', 'Check'],
                ['ICT/SRF/1', ['value' => 45000, 'format' => 'd-mmm-yy'], 'Budi Santoso', 2, ['formula' => '=D2*2']],
                ['ICT/SRF/2', null, 'Andi Wijaya', 10, null],
            ],
            'COF' => [
                ['No', 'COF No', 'Catatan', 'Tautan'],
                [1, 'ICT/COF/1', 'Menyusul', self::SP_FOLDER_URL . '/ICTCOF1 - Budi Santoso.pdf'],
                [2, 'ICT/COF/2', null, self::SP_FOLDER_URL . '/ICTCOF2 - Andi Wijaya.pdf'],
                [3, 'ICT/COF/3', null, null],
            ],
        ];
    }

    /** What the folder holds: a folder, and three scans (the second named with two spaces, the third without a row). */
    protected static function spFiles(): array
    {
        $file = fn (string $id, string $name, string $modified, int $size = 1000) => [
            'id' => $id, 'name' => $name, 'size' => $size, 'file' => ['mimeType' => 'application/pdf'],
            'lastModifiedDateTime' => $modified, 'webUrl' => self::SP_FOLDER_URL . '/' . rawurlencode($name),
            'lastModifiedBy' => ['user' => ['displayName' => 'Petugas Arsip']],
        ];

        return [
            ['id' => 'DIR1', 'name' => 'Lama', 'size' => 0, 'folder' => ['childCount' => 0], 'lastModifiedDateTime' => '2025-12-01T00:00:00Z', 'webUrl' => self::SP_FOLDER_URL . '/Lama'],
            $file('PDF1', 'ICTCOF1 - Budi Santoso.pdf', '2026-01-03T00:00:00Z'),
            $file('PDF2', 'ICTCOF2 - Andi  Wijaya.pdf', '2026-01-02T00:00:00Z'),
            $file('PDF9', 'ICTCOF9 - Tanpa Baris.pdf', '2026-01-01T00:00:00Z', 50 * 1024 * 1024),
        ];
    }

    protected function spWritten(string $sheet, string $cell): ?string
    {
        return (new XlsxPatcher())->read($this->spBook, $sheet, $cell)['raw'];
    }

    private function spBookItem(): array
    {
        return [
            'id' => 'BOOK1', 'name' => 'Daftar Induk.xlsx', 'size' => strlen($this->spBook), 'eTag' => '"{BOOK},' . $this->spSaves . '"',
            'file' => ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'lastModifiedDateTime' => '2026-10-06T07:01:28Z',
            'webUrl' => 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D',
            'parentReference' => ['driveId' => 'b!drive-1'],
        ];
    }

    private function spSave(GraphRequest $request)
    {
        $this->spSaved[] = $request->body();
        $this->spBook = $request->body();
        $this->spSaves++;

        return Http::response($this->spBookItem());
    }

    /** The address in "/shares/u!<base64>/driveItem" is the workbook's or the folder's. */
    private function spIsTheWorkbook(string $url): bool
    {
        $encoded = substr(explode('/', substr($url, strlen('https://graph.microsoft.com/v1.0/shares/')))[0], 2);

        return str_contains((string) base64_decode(strtr($encoded, '-_', '+/')), 'Doc.aspx');
    }
}
