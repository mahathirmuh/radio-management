<?php

namespace App\Services\Audit;

/**
 * The browser and the system a request came from, in a few words ("Chrome 141 on Windows"), from the User-Agent it sent: the
 * "Browser" line of an entry of the audit trail. Only the browsers that matter here are known; a program is named by the first
 * name it gives ("curl 8", "PostmanRuntime 7"), anything else is shown as it was sent.
 */
final class UserAgent
{
    /** [pattern, name]: the first that matches names the browser, the number it catches is the version. (Edge and Opera say Chrome too.) */
    private const BROWSERS = [
        ['~\bEdg(?:e|A|iOS)?/(\d+)~', 'Edge'],
        ['~\bOPR/(\d+)~', 'Opera'],
        ['~\bSamsungBrowser/(\d+)~', 'Samsung Internet'],
        ['~\b(?:Firefox|FxiOS)/(\d+)~', 'Firefox'],
        ['~\b(?:Chrome|CriOS)/(\d+)~', 'Chrome'],
        ['~\bVersion/(\d+)[\d.]*(?: Mobile/\S+)? Safari/~', 'Safari'],
    ];

    /** [pattern, name] of the system. (Android says Linux, an iPhone "like Mac OS X".) */
    private const SYSTEMS = [
        ['~Windows NT~', 'Windows'],
        ['~Android~', 'Android'],
        ['~iPhone|iPad|iPod~', 'iOS'],
        ['~Macintosh|Mac OS X~', 'macOS'],
        ['~CrOS~', 'ChromeOS'],
        ['~Linux~', 'Linux'],
    ];

    /** At most this many characters of a User-Agent that is shown as it was sent. */
    public const MOST = 120;

    /**
     * @param string $on      the word between the browser and its system, in the language of the page
     * @param string $console what the command line of the server is called there
     */
    public static function describe(?string $agent, string $on = 'on', string $console = 'Command line'): ?string
    {
        $agent = trim((string) $agent);
        if ($agent === '') {
            return null;
        }
        if ($agent === AuditLogger::CONSOLE) {
            return $console;
        }

        foreach (self::BROWSERS as [$pattern, $name]) {
            if (preg_match($pattern, $agent, $match)) {
                $system = self::system($agent);

                return $name . ' ' . $match[1] . ($system === null ? '' : ' ' . $on . ' ' . $system);
            }
        }
        // A program: by the first name it gives, with the first number of its version ("curl/8.4.0" => "curl 8").
        if (preg_match('~^([A-Za-z][\w.-]*)(?:/(\d+))?~', $agent, $match) && $match[1] !== 'Mozilla') {
            return $match[1] . (isset($match[2]) ? ' ' . $match[2] : '');
        }

        return mb_substr($agent, 0, self::MOST);
    }

    private static function system(string $agent): ?string
    {
        foreach (self::SYSTEMS as [$pattern, $name]) {
            if (preg_match($pattern, $agent)) {
                return $name;
            }
        }

        return null;
    }
}
