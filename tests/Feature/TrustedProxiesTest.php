<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Behind a reverse proxy that ends HTTPS (the Docker container behind Nginx Proxy Manager), the request comes in as
 * plain HTTP from the proxy, and the proxy says in X-Forwarded-* what the visitor asked for. Laravel believes that only
 * from the proxies named in TRUSTED_PROXIES (config/trustedproxy.php).
 */
class TrustedProxiesTest extends TestCase
{
    /**
     * The login page as it comes back for a request from $from to the container (http://10.0.0.5:8020, the address a proxy
     * forwards to) that says it was https://radio.example.test. (The address is given in full: a relative one would be
     * taken from the request before, as the test client does.)
     */
    private function loginPageFrom(string $from): string
    {
        return (string) $this->withServerVariables(['REMOTE_ADDR' => $from])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'radio.example.test', 'X-Forwarded-Port' => '443'])
            ->get('http://10.0.0.5:8020/login')->assertOk()->getContent();
    }

    public function test_nobody_is_believed_unless_named(): void
    {
        config(['trustedproxy.proxies' => null]);

        // Whoever can reach the port does not get to say what the scheme and the host were.
        $page = $this->loginPageFrom('10.1.2.3');
        $this->assertStringNotContainsString('https://radio.example.test/', $page);
        $this->assertStringContainsString('http://10.0.0.5:8020/assets/', $page);
    }

    public function test_the_named_proxy_is_believed_and_the_pages_link_with_https(): void
    {
        // As TRUSTED_PROXIES gives it: comma separated, an address and a range.
        config(['trustedproxy.proxies' => '10.1.2.3, 172.16.0.0/12']);

        // From the proxy: the assets of the page are linked the way the visitor opened it, or the browser would block them.
        $this->assertStringContainsString('https://radio.example.test/assets/', $this->loginPageFrom('10.1.2.3'));
        $this->assertStringContainsString('https://radio.example.test/assets/', $this->loginPageFrom('172.18.0.1'));

        // From anywhere else, the headers are words of a stranger.
        $page = $this->loginPageFrom('10.9.9.9');
        $this->assertStringNotContainsString('https://radio.example.test/', $page);
        $this->assertStringContainsString('http://10.0.0.5:8020/assets/', $page);
    }

    public function test_with_an_https_app_url_the_pages_link_with_https_even_when_the_proxy_is_not_believed(): void
    {
        // As on the production server: APP_URL is the https address, and the proxy arrives from an address that
        // TRUSTED_PROXIES does not name. (APP_URL is looked at when the application starts: its start is run again.)
        config(['app.url' => 'https://radio.example.test', 'trustedproxy.proxies' => '10.1.2.3']);
        $this->app->getProvider(AppServiceProvider::class)->boot($this->app['request']);

        $page = (string) $this->withServerVariables(['REMOTE_ADDR' => '192.168.48.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.7'])
            ->get('http://radio.example.test/login')->assertOk()->getContent();

        // The assets and the forms are linked the way the visitor opened the page ...
        $this->assertStringContainsString('https://radio.example.test/assets/', $page);
        $this->assertStringNotContainsString('http://radio.example.test/', $page);
        // ... while the address of the visitor still comes only from a believed proxy.
        $this->assertSame('192.168.48.1', request()->ip());
    }

    public function test_with_an_http_app_url_nothing_is_forced(): void
    {
        // The laptop (and the tests): APP_URL is http, links follow the request as before.
        $this->assertStringStartsWith('http://', (string) config('app.url'));
        config(['trustedproxy.proxies' => null]);

        $page = $this->loginPageFrom('10.1.2.3');
        $this->assertStringContainsString('http://10.0.0.5:8020/assets/', $page);
        $this->assertStringNotContainsString('https://', implode(' ', preg_match_all('#(?:href|src)="(https?://[^"]+/assets/[^"]*)"#', $page, $m) ? $m[1] : []));
    }

    public function test_the_setting_is_read_from_the_environment(): void
    {
        $settings = require config_path('trustedproxy.php');
        $this->assertNull($settings['proxies'], 'phpunit.xml and an .env without it name no proxy');
    }
}
