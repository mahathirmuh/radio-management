<?php

namespace Tests\Feature;

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

    public function test_the_setting_is_read_from_the_environment(): void
    {
        $settings = require config_path('trustedproxy.php');
        $this->assertNull($settings['proxies'], 'phpunit.xml and an .env without it name no proxy');
    }
}
