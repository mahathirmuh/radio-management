<?php

namespace App\Services;

use App\Models\UserManagement\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * The languages of the screens: Indonesian, the default, and English. A person picks one with the switch in the account
 * menu (or on the sign-in page); it is kept with the account (users.locale) and in the browser (the cookie "locale",
 * which the sign-in page and the error pages go by). The texts are in lang/<code>/ and modules/<Module>/Resources/lang/.
 *
 * Never translated: what people typed and the master data, the content of SharePoint, the printed checkout form, the
 * Excel files of import and export (their columns are a format), and what was stored before (notifications, the audit
 * log). The REST API speaks English unless a client asks for Indonesian (Accept-Language).
 */
final class Language
{
    public const DEFAULT = 'id';

    /** Each language by its own name, as the switch shows it. */
    public const NAMES = ['id' => 'Bahasa Indonesia', 'en' => 'English'];

    /** The cookie of the browser's choice. Not encrypted (EncryptCookies), so that a request without a session can read it. */
    public const COOKIE = 'locale';

    /** How long the browser keeps the choice: a year. */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    /** $code when it is a language of the screens ("id", "en"), else null. */
    public static function valid(mixed $code): ?string
    {
        $code = is_string($code) ? strtolower(trim($code)) : '';

        return array_key_exists($code, self::NAMES) ? $code : null;
    }

    /** The language of $user: their choice, else Indonesian. What a notification for them is written in. */
    public static function of(?User $user): string
    {
        return self::valid($user?->locale) ?? self::DEFAULT;
    }

    /**
     * The language of a page: the account's choice, else the browser's, else Indonesian. Before the session has started
     * (an address that is no page, the maintenance page) only the browser's is known.
     */
    public static function forRequest(Request $request): string
    {
        $user = $request->hasSession() ? $request->user() : null;

        return self::valid($user?->locale) ?? self::valid($request->cookies->get(self::COOKIE)) ?? self::DEFAULT;
    }

    /** The language of the REST API: English, unless the client prefers Indonesian (Accept-Language: id). */
    public static function forApi(Request $request): string
    {
        foreach ($request->getLanguages() as $language) {
            $code = self::valid(strtok(str_replace('_', '-', $language), '-'));
            if ($code !== null) {
                return $code;
            }
        }

        return 'en';
    }

    /** Uses $code for the rest of the request: the texts, and the names of days and months in dates. */
    public static function use(string $code): void
    {
        App::setLocale($code);
        Carbon::setLocale($code);
    }

    /** A whole number as the language writes it: 1.011 in Indonesian, 1,011 in English. */
    public static function number(int|float $value, int $decimals = 0): string
    {
        return App::getLocale() === 'en'
            ? number_format($value, $decimals, '.', ',')
            : number_format($value, $decimals, ',', '.');
    }
}
