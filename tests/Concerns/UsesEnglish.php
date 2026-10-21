<?php

namespace Tests\Concerns;

use App\Services\Language;

/**
 * For the tests of what is checked in English: the requests of the test come from a browser that chose English (the
 * cookie of the language switch, also on the JSON requests, which carry cookies only "with credentials"), and what the
 * test asks of the application directly is in English too.
 */
trait UsesEnglish
{
    protected function useEnglish(): void
    {
        $this->withUnencryptedCookie(Language::COOKIE, 'en');
        $this->withCredentials();
        Language::use('en');
    }
}
