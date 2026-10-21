<?php

namespace App\Http\Middleware;

use App\Services\Language;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // "id" or "en", nothing secret: read before the session (and the decryption) has started (SetLocale).
        Language::COOKIE,
    ];
}
