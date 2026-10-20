<?php

namespace Tests\Unit;

use App\Services\Audit\AuditLogger;
use App\Services\Audit\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The "Browser" line of an entry of the audit trail: the browser and the system from the User-Agent that was kept.
 */
class UserAgentTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function agents(): array
    {
        return [
            'Chrome on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Chrome 141 on Windows'],
            // Edge, Opera and Samsung Internet say Chrome too.
            'Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.3537.71', 'Edge 141 on Windows'],
            'Opera' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 OPR/110.0.0.0', 'Opera 110 on Windows'],
            'Samsung Internet' => ['Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', 'Samsung Internet 26 on Android'],
            // Android says Linux; an iPhone says "like Mac OS X".
            'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'Chrome 141 on Android'],
            'Chrome on an iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/141.0.7390.41 Mobile/15E148 Safari/604.1', 'Chrome 141 on iOS'],
            'Safari on an iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari 18 on iOS'],
            'Safari on a Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', 'Safari 18 on macOS'],
            'Firefox on Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Firefox 131 on Linux'],
            'Chrome on a Chromebook' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Chrome 141 on ChromeOS'],
            // A program that calls the API: by the name it gives.
            'curl' => ['curl/8.4.0', 'curl 8'],
            'Postman' => ['PostmanRuntime/7.39.0', 'PostmanRuntime 7'],
            'Power BI' => ['Microsoft.Data.Mashup (https://go.microsoft.com/fwlink/?LinkID=304225)', 'Microsoft.Data.Mashup'],
            // The command line of the server; nothing kept.
            'console' => [AuditLogger::CONSOLE, 'Command line'],
            'nothing' => ['', null],
            'blank' => ['   ', null],
            'null' => [null, null],
            // Anything else as it was sent, cut short.
            'a robot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            'a long one' => ['Mozilla/5.0 ' . str_repeat('x', 300), 'Mozilla/5.0 ' . str_repeat('x', UserAgent::MOST - 12)],
        ];
    }

    #[DataProvider('agents')]
    public function test_a_user_agent_is_named_by_its_browser_and_system(?string $agent, ?string $named): void
    {
        $this->assertSame($named, UserAgent::describe($agent));
    }
}
