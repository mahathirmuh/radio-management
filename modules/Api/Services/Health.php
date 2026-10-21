<?php

namespace Modules\Api\Services;

use App\Services\Directory\LdapAuthenticator;
use App\Services\Language;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\SharePointDocs\Services\GraphClient;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SnipeIt\Services\Rows;
use Modules\SnipeIt\Services\SnipeItClient;
use Modules\SnipeIt\Services\SnipeItUnavailable;
use Throwable;

/**
 * How the application is (GET /api/v1/health): whether the website works, down to its database (the login page served as
 * to a browser, the database with the application's own tables, the storage, the sessions), and, for a client with a
 * token, everything it depends on: the cache, the migrations, Snipe-IT, Microsoft, Active Directory, the HR database.
 * Every check says ok, warn (works, but needs a look), fail or skipped (not set up here), how long it took, and why it is
 * not ok as a word (a reason) and a sentence in the client's language. Nothing names a server, a path or an error of the
 * server: those are in the log.
 */
final class Health
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const SKIPPED = 'skipped';

    /** The checks without which the website does not work: one failing is "down". They are run without a token too. */
    private const ESSENTIAL = ['web', 'database', 'storage', 'session'];

    /** The application's own tables that the database check reads: the accounts, the sidebar, the register. */
    private const TABLES = ['users', 'menu', 'radios'];

    /** Seconds Microsoft may take to sign the application in. */
    private const MICROSOFT_SECONDS = 8.0;

    public function __construct(
        private readonly SnipeItClient $snipeIt,
        private readonly GraphClient $graph,
        private readonly LdapAuthenticator $directory,
    ) {
    }

    /**
     * @return array{status: string, version: ?string, time: string, checks: array<string, array<string, mixed>>}
     *     status: ok; degraded (something it depends on fails or needs a look); down (the website or its database fails);
     *     checks: without $detail only the status of each essential check
     */
    public function report(bool $detail): array
    {
        $checks = ['web' => $this->web(), 'database' => $this->database(), 'storage' => $this->storage(), 'session' => $this->session()];
        if ($detail) {
            $checks += [
                'cache' => $this->cache(),
                'migrations' => $this->migrations($checks['database']['status'] === self::OK),
                'snipe_it' => $this->snipeIt(),
                'microsoft' => $this->microsoft(),
                'ldap' => $this->ldap(),
                'hr_database' => $this->hrDatabase(),
            ];
        }

        $failing = fn (array $check) => in_array($check['status'], [self::FAIL, self::WARN], true);
        $status = match (true) {
            collect(self::ESSENTIAL)->contains(fn (string $name) => $checks[$name]['status'] === self::FAIL) => 'down',
            collect($checks)->contains($failing) => 'degraded',
            default => 'ok',
        };

        return [
            'status' => $status,
            'version' => config('app.version'),
            'time' => now()->toIso8601String(),
            'checks' => $detail ? $checks : array_map(fn (array $check) => ['status' => $check['status']], $checks),
        ];
    }

    /**
     * The login page, asked of the application as a browser asks for it: GET /login through everything a page goes through
     * (the session, the language, the templates), given back whole with its form. As a browser of its own: no account and
     * no session of this request (they are put back afterwards). Nothing is written: the activity log keeps no GET, and the
     * session the page was given is taken away again.
     */
    private function web(): array
    {
        $main = app('request');
        $locale = app()->getLocale();
        $kept = ['auth' => app('auth'), 'session' => app('session'), 'session.store' => app('session.store')];

        return $this->timed('web', function () use ($main, $locale, $kept) {
            $sessions = new SessionManager(app());
            app()->instance('session', $sessions);
            app()->instance('session.store', $sessions->driver());
            app()->instance('auth', new AuthManager(app()));
            foreach (['auth', 'session'] as $facade) {
                Facade::clearResolvedInstance($facade);
            }
            $page = Request::create('/login', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'health-check']);
            try {
                $response = app(HttpKernel::class)->handle($page);
            } finally {
                // The page was a request of its own: this one is the request again, with its account, its session and its language.
                if ($page->hasSession()) {
                    $page->session()->getHandler()->destroy($page->session()->getId());
                }
                foreach ($kept as $name => $instance) {
                    app()->instance($name, $instance);
                }
                app()->instance('request', $main);
                foreach (['auth', 'session', 'request'] as $facade) {
                    Facade::clearResolvedInstance($facade);
                }
                Language::use($locale);
            }
            if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->getContent(), 'id="loginForm"')) {
                Log::warning('Health check web: the login page was not served.', ['status' => $response->getStatusCode()]);

                return ['status' => self::FAIL, 'reason' => 'not_working'];
            }

            return [];
        }, 'not_working');
    }

    /** The database answers, and the application's own tables can be read (only read: nothing is written). */
    private function database(): array
    {
        return $this->timed('database', function () {
            DB::connection()->select('select 1 as up');
            foreach (self::TABLES as $table) {
                try {
                    DB::table($table)->exists();
                } catch (Throwable $unreadable) {
                    Log::warning("Health check database: the table {$table} cannot be read.", ['error' => get_class($unreadable)]);

                    return ['status' => self::FAIL, 'reason' => 'tables'];
                }
            }

            return [];
        }, 'unreachable');
    }

    /** A session is written, read back and taken away again, where the sessions of the pages are kept. */
    private function session(): array
    {
        return $this->timed('session', function () {
            $handler = app('session')->driver()->getHandler();
            $id = Str::random(40);
            $written = serialize(['health' => Str::random(16)]);
            $handler->write($id, $written);
            $read = $handler->read($id);
            $handler->destroy($id);
            if ($read !== $written) {
                throw new \RuntimeException('The sessions do not give back what was written.');
            }

            return ['driver' => (string) config('session.driver')];
        }, 'not_writable');
    }

    /** A file is written, read back and taken away again where the application keeps its own (sessions, views, cache). */
    private function storage(): array
    {
        return $this->timed('storage', function () {
            $file = storage_path('framework/health-' . Str::random(16) . '.tmp');
            $written = Str::random(32);
            try {
                if (@file_put_contents($file, $written) === false || @file_get_contents($file) !== $written) {
                    throw new \RuntimeException('The storage folder cannot be written.');
                }
            } finally {
                @unlink($file);
            }

            return [];
        }, 'not_writable');
    }

    private function cache(): array
    {
        return $this->timed('cache', function () {
            $key = 'health-check:' . Str::random(16);
            $written = Str::random(32);
            Cache::put($key, $written, 30);
            $read = Cache::get($key);
            Cache::forget($key);
            if ($read !== $written) {
                throw new \RuntimeException('The cache did not give back what was put in it.');
            }

            return ['store' => (string) config('cache.default')];
        }, 'not_writable');
    }

    /** Migrations that have not been run on this database yet (the user's to run): by their names. */
    private function migrations(bool $databaseUp): array
    {
        if (! $databaseUp) {
            return $this->skipped('database');
        }

        return $this->timed('migrations', function () {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                return ['status' => self::FAIL, 'reason' => 'no_table'];
            }
            $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
            $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
            sort($pending);

            return $pending === []
                ? ['pending' => 0]
                : ['status' => self::WARN, 'reason' => 'pending', 'pending' => count($pending), 'names' => $pending];
        }, 'unreachable');
    }

    /** Snipe-IT answers its token (GET version: also its version). Only asked. */
    private function snipeIt(): array
    {
        if (! SnipeItClient::configured()) {
            return $this->skipped('not_configured');
        }

        return $this->timed('snipe_it', function () {
            try {
                $answer = $this->snipeIt->get('version');
            } catch (SnipeItUnavailable $unavailable) {
                return ['status' => self::FAIL, 'reason' => $unavailable->reason];
            }

            return ['version' => Rows::text($answer['version'] ?? null)];
        }, 'unreachable');
    }

    /** Microsoft signs the application in (its credentials, for the SharePoint pages and the sign-in with Microsoft). */
    private function microsoft(): array
    {
        if (! $this->graph->configured()) {
            return $this->skipped('not_configured');
        }

        return $this->timed('microsoft', function () {
            try {
                $this->graph->within(self::MICROSOFT_SECONDS, fn () => $this->graph->signIn());
            } catch (GraphException $refused) {
                return ['status' => self::FAIL, 'reason' => $refused->reason === GraphException::CREDENTIALS ? 'refused' : 'unreachable'];
            }

            return [];
        }, 'unreachable');
    }

    /** Active Directory: the service account signs in and looks a name up (one that is nobody's). */
    private function ldap(): array
    {
        if (! $this->directory->isConfigured()) {
            return $this->skipped('not_configured');
        }

        // (Not reached, or the service account refused: the directory says only that it cannot be asked.)
        return $this->timed('ldap', function () {
            $this->directory->lookup('health-check-probe');

            return [];
        }, 'unavailable');
    }

    /** The HR database ("Find employee"), read only. */
    private function hrDatabase(): array
    {
        $connection = (string) config('employee_directory.connection', 'hr');
        if (! config('employee_directory.enabled', true) || blank(config("database.connections.{$connection}.host"))) {
            return $this->skipped('not_configured');
        }

        return $this->timed('hr_database', function () use ($connection) {
            DB::connection($connection)->select('select 1 as up');

            return [];
        }, 'unreachable');
    }

    /**
     * Runs a check and times it: ok with what it gives back, or its own status; a failure with $reason when it throws (what
     * was thrown goes to the log only).
     */
    private function timed(string $name, callable $check, string $reason): array
    {
        $started = microtime(true);
        try {
            $result = $check();
            $status = $result['status'] ?? self::OK;
        } catch (Throwable $failure) {
            Log::warning("Health check {$name} failed.", ['error' => get_class($failure), 'message' => Str::limit($failure->getMessage(), 300)]);
            $result = ['reason' => $reason];
            $status = self::FAIL;
        }
        $result['status'] = $status;
        $result['ms'] = (int) round((microtime(true) - $started) * 1000);

        return $this->said(['status' => $status, 'ms' => $result['ms']] + $result);
    }

    private function skipped(string $reason): array
    {
        return $this->said(['status' => self::SKIPPED, 'ms' => null, 'reason' => $reason]);
    }

    /** The sentence of a check that is not ok, in the client's language. */
    private function said(array $check): array
    {
        if (isset($check['reason'])) {
            $check['message'] = trans_choice('Api::api.health.' . $check['reason'], (int) ($check['pending'] ?? 1), ['count' => $check['pending'] ?? 1]);
        }

        return $check;
    }
}
