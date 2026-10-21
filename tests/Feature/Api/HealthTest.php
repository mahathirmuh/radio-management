<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesDirectory;
use Tests\Concerns\FakesSnipeIt;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\UsesApi;
use Tests\TestCase;

/**
 * Whether the application is up: /up for the container (nothing asked), /api/v1/health for a watcher (the database and
 * the storage; with a token, everything the application depends on). Nothing in an answer names a server or an error of
 * the server.
 */
class HealthTest extends TestCase
{
    use FakesDirectory;
    use FakesSnipeIt;
    use MakesUsers;
    use RefreshDatabase;
    use UsesApi;

    protected bool $seed = true;

    /** The test's own database by its name: one test makes the default connection one that does not answer. */
    protected array $connectionsToTransact = ['sqlsrv'];

    private const CHECKS = ['database', 'storage', 'cache', 'migrations', 'snipe_it', 'microsoft', 'ldap', 'hr_database'];

    /** The answer with a token: the whole, and each check by its name. */
    private function detail(array $headers = []): array
    {
        $token = $this->tokenFor($this->makeUser('watcher-' . uniqid() . '@test.local', 'Viewer Radio'), write: false);

        return $this->api('GET', '/api/v1/health', token: $token, headers: $headers)->assertOk()->json('data');
    }

    public function test_up_answers_without_a_database_a_session_or_a_token(): void
    {
        DB::enableQueryLog();
        $response = $this->get('/up')->assertOk()->assertExactJson(['status' => 'up']);
        $this->assertSame([], DB::getQueryLog(), 'nothing is asked of the database');
        $response->assertCookieMissing((string) config('session.cookie'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_without_a_token_it_says_only_whether_the_application_works_and_its_version(): void
    {
        $response = $this->api('GET', '/api/v1/health')->assertOk();
        $this->assertSame(['status', 'version', 'time'], array_keys($response->json('data')));
        $this->assertSame(['ok', config('app.version')], [$response->json('data.status'), $response->json('data.version')]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $response->json('data.time'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_with_a_token_every_check_and_what_is_not_set_up_is_skipped(): void
    {
        $data = $this->detail();

        $this->assertSame('ok', $data['status']);
        $this->assertSame(self::CHECKS, array_keys($data['checks']));
        foreach (['database', 'storage'] as $name) {
            $this->assertSame(['status', 'ms'], array_keys($data['checks'][$name]), $name);
            $this->assertSame('ok', $data['checks'][$name]['status'], $name);
            $this->assertIsInt($data['checks'][$name]['ms']);
        }
        $this->assertSame(['status' => 'ok', 'ms' => $data['checks']['cache']['ms'], 'store' => 'array'], $data['checks']['cache']);
        $this->assertSame(['status' => 'ok', 'ms' => $data['checks']['migrations']['ms'], 'pending' => 0], $data['checks']['migrations']);
        // Not set up here (the tests reach nothing outside): skipped, which is no failure.
        foreach (['snipe_it', 'microsoft', 'ldap', 'hr_database'] as $name) {
            $this->assertSame(['status' => 'skipped', 'ms' => null, 'reason' => 'not_configured', 'message' => 'Not set up here.'], $data['checks'][$name], $name);
        }
        Http::assertNothingSent();
    }

    public function test_a_service_that_fails_makes_it_degraded_and_says_why_in_the_clients_language(): void
    {
        // Snipe-IT answers, with its version; Microsoft signs the application in; the directory and the HR database answer.
        $this->fakeSnipeIt();
        config(['services.azure.tenant' => 'tenant-1', 'services.azure.client_id' => 'client-1', 'services.azure.client_secret' => 'secret-1']);
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3599])]);
        $this->fakeDirectory();
        config(['database.connections.hr' => config('database.connections.sqlsrv')]);

        $data = $this->detail();
        $this->assertSame('ok', $data['status']);
        $this->assertSame(['status' => 'ok', 'ms' => $data['checks']['snipe_it']['ms'], 'version' => 'v6.2.4-pre'], $data['checks']['snipe_it']);
        foreach (['microsoft', 'ldap', 'hr_database'] as $name) {
            $this->assertSame('ok', $data['checks'][$name]['status'], $name);
        }
        $this->assertSame(['GET'], array_values(array_unique(array_column($this->snipeAsked, 'method'))));

        // Snipe-IT out of reach, the directory down, the database of HR gone: degraded (the application itself works), 200.
        $this->fakeSnipeIt(['version' => fn () => Http::failedConnection('cURL error 7: Failed to connect to snipeit.test port 80')]);
        $this->fakeDirectory(down: true);
        config(['database.connections.hr.host' => 'hr-database.invalid', 'database.connections.hr.database' => 'nothing']);
        DB::purge('hr');
        $response = $this->api('GET', '/api/v1/health', token: $this->tokenFor($this->superAdmin()), headers: ['Accept-Language' => 'id'])->assertOk();
        $data = $response->json('data');
        $this->assertSame('degraded', $data['status']);
        $this->assertSame(['fail', 'unreachable', 'Tidak bisa dihubungi.'], [$data['checks']['snipe_it']['status'], $data['checks']['snipe_it']['reason'], $data['checks']['snipe_it']['message']]);
        $this->assertSame(['fail', 'unavailable', 'Tidak bisa ditanya: tidak terhubung, atau kredensialnya ditolak.'],
            [$data['checks']['ldap']['status'], $data['checks']['ldap']['reason'], $data['checks']['ldap']['message']]);
        $this->assertSame(['fail', 'unreachable'], [$data['checks']['hr_database']['status'], $data['checks']['hr_database']['reason']]);
        // Nothing of the server's: no address, no error it gave.
        foreach (['snipeit.test', 'hr-database.invalid', 'cURL', 'SQLSTATE', 'could not be reached'] as $server) {
            $this->assertStringNotContainsString($server, $response->getContent(), $server);
        }
    }

    public function test_microsoft_refusing_the_credentials_is_told_from_microsoft_out_of_reach(): void
    {
        config(['services.azure.tenant' => 'tenant-1', 'services.azure.client_id' => 'client-1', 'services.azure.client_secret' => 'wrong']);
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => 'AADSTS7000215: Invalid client secret.'], 401)]);
        $check = $this->detail()['checks']['microsoft'];
        $this->assertSame(['fail', 'refused', 'Refused the credentials.'], [$check['status'], $check['reason'], $check['message']]);
    }

    public function test_migrations_not_run_yet_are_named(): void
    {
        DB::table('migrations')->whereIn('migration', ['2026_10_21_110000_add_snipe_it_menu', '2026_10_21_100000_add_locale_to_users'])->delete();

        $data = $this->detail();
        $this->assertSame('degraded', $data['status']);
        $this->assertSame([
            'status' => 'warn', 'ms' => $data['checks']['migrations']['ms'], 'reason' => 'pending', 'pending' => 2,
            'names' => ['2026_10_21_100000_add_locale_to_users', '2026_10_21_110000_add_snipe_it_menu'],
            'message' => '2 migrations have not been run on this database yet.',
        ], $data['checks']['migrations']);

        DB::table('migrations')->insert(['migration' => '2026_10_21_100000_add_locale_to_users', 'batch' => 99]);
        $this->assertSame('1 migrasi belum dijalankan di database ini.', $this->detail(['Accept-Language' => 'id'])['checks']['migrations']['message']);
    }

    public function test_without_its_database_it_is_down_with_503_and_a_token_changes_nothing(): void
    {
        $token = $this->tokenFor($this->superAdmin());
        config(['database.connections.gone' => ['host' => 'database.invalid', 'database' => 'nothing'] + config('database.connections.sqlsrv'), 'database.default' => 'gone']);

        $response = $this->api('GET', '/api/v1/health')->assertStatus(503);
        $this->assertSame(['status' => 'down', 'version' => config('app.version')], array_intersect_key($response->json('data'), ['status' => 1, 'version' => 1]));
        // The token cannot be looked at without the database: the same answer, without the details.
        $withToken = $this->api('GET', '/api/v1/health', token: $token)->assertStatus(503);
        $this->assertSame(['status', 'version', 'time'], array_keys($withToken->json('data')));
        foreach (['database.invalid', 'SQLSTATE'] as $server) {
            $this->assertStringNotContainsString($server, $withToken->getContent(), $server);
        }
        // The container's own check still answers.
        $this->get('/up')->assertOk();
    }

    public function test_a_token_that_is_none_is_refused_like_everywhere_in_the_api(): void
    {
        $this->api('GET', '/api/v1/health', token: '1|' . str_repeat('x', 40))->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
        $sleeper = $this->makeUser('sleeper@test.local', 'Viewer Radio');
        $token = $this->tokenFor($sleeper);
        $sleeper->forceFill(['active' => '0'])->save();
        $this->api('GET', '/api/v1/health', token: $token, headers: ['Accept-Language' => 'id'])->assertStatus(401)->assertExactJson(['message' => 'Tidak terautentikasi.']);
    }

    public function test_the_documentation_has_it_without_a_token(): void
    {
        $this->actingAs($this->superAdmin());
        $spec = $this->get('/docs/api.json')->assertOk()->json();
        $this->assertArrayHasKey('/health', $spec['paths']);
        $this->assertSame([], $spec['paths']['/health']['get']['security'] ?? null);
        $this->assertSame('Health of the application', $spec['paths']['/health']['get']['summary'] ?? null);
    }
}
