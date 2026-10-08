<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Modules\RadioManagement\Models\Radio;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\MakesWorkbooks;
use Tests\TestCase;

/**
 * Opens every GET route as Super Admin and fails on any server error. The base app
 * was written against MySQL; this catches queries that SQL Server rejects before a
 * user runs into them.
 */
class RouteSmokeTest extends TestCase
{
    use MakesUsers;
    use MakesWorkbooks;
    use RefreshDatabase;

    protected bool $seed = true;

    /** Routes that leave the app (SSO), need an OAuth code, or belong to dev tooling. */
    private const SKIP = [
        '#^auth/(google|microsoft|merdeka)/#',
        '#^_debugbar#',
        '#^_ignition#',
        '#^sanctum/#',
        '#^storage/#',
        '#^up$#',
    ];

    public function test_no_get_route_ends_in_a_server_error(): void
    {
        $radio = Radio::create(['dmr_id' => 20, 'serial_number' => '867TZB5309', 'user_name' => 'Iqbal']);
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        // The SharePoint pages read Microsoft Graph: a pretend one answers, with an empty folder
        // and a small workbook. Every configured folder and workbook gets a made-up address, so
        // the result does not depend on .env.
        config(['services.azure.tenant' => 'tenant-1', 'services.azure.client_id' => 'client-1', 'services.azure.client_secret' => 'secret-1']);
        foreach (array_keys(config('sharepoint-docs.folders')) as $folder) {
            config(["sharepoint-docs.folders.{$folder}.url" => "https://contoso.sharepoint.com/sites/Team/Shared%20Documents/{$folder}"]);
        }
        $workbooks = [];
        foreach (array_keys(config('sharepoint-docs.workbooks')) as $workbook) {
            $address = "https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?file={$workbook}.xlsx";
            config(["sharepoint-docs.workbooks.{$workbook}.url" => $address]);
            $workbooks['graph.microsoft.com/v1.0/shares/u!' . rtrim(strtr(base64_encode($address), '+/', '-_'), '=') . '/*'] = Http::response([
                'id' => 'BOOK1', 'name' => "{$workbook}.xlsx", 'file' => ['mimeType' => 'application/vnd.ms-excel'], 'parentReference' => ['driveId' => 'b!drive-1'],
            ]);
        }
        Http::fake($workbooks + [
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599]),
            'graph.microsoft.com/v1.0/shares/*' => Http::response(['id' => 'ROOT1', 'name' => 'Scans', 'folder' => ['childCount' => 0], 'parentReference' => ['driveId' => 'b!drive-1']]),
            'graph.microsoft.com/v1.0/drives/b!drive-1/items/BOOK1/content' => Http::response(self::xlsx(['Sheet1' => [['No', 'Nama'], ['1', 'Contoh']]])),
            'graph.microsoft.com/v1.0/drives/*' => Http::response(['value' => []]),
        ]);

        $checked = 0;
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            foreach (self::SKIP as $pattern) {
                if (preg_match($pattern, $uri)) {
                    continue 2;
                }
            }

            // Routes with parameters get a row that exists where one is known, else id 1
            // (seeded roles, menus and master data start there); a 404 is fine.
            $id = match (true) {
                str_contains($uri, '{radio}') => $radio->id,
                str_starts_with($uri, 'user-management/user-registered') => $admin->id,
                default => 1,
            };
            $url = '/' . ltrim(preg_replace('/\{[^}]+\}/', (string) $id, $uri), '/');
            $response = $this->get($url);
            $checked++;

            if ($response->getStatusCode() >= 500) {
                $message = $response->exception?->getMessage() ?? '';
                $failures[] = sprintf('%s [%s] -> %d %s', $url, $route->getName() ?? '-', $response->getStatusCode(), mb_strimwidth($message, 0, 300, '…'));
            }
        }

        $this->assertGreaterThan(30, $checked, 'Expected to exercise the application routes.');
        $this->assertSame([], $failures, "Routes ending in a server error:\n" . implode("\n", $failures));
    }
}
