<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\RadioManagement\Models\Radio;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * Opens every GET route as Super Admin and fails on any server error. The base app
 * was written against MySQL; this catches queries that SQL Server rejects before a
 * user runs into them.
 */
class RouteSmokeTest extends TestCase
{
    use MakesUsers;
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
