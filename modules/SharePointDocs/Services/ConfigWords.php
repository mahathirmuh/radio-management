<?php

namespace Modules\SharePointDocs\Services;

use App\Services\Language;
use Illuminate\Support\Facades\Lang;

/**
 * The words of config/sharepoint-docs.php in the language of the page: the titles of the two columns a file name is split
 * into. They are SharePointDocs::scans.folders.<key> while the config still says what their Indonesian ones say;
 * otherwise the config's own words (it was changed after the words were written, or it has a folder they do not know).
 * The API keeps the config's words.
 */
final class ConfigWords
{
    public static function of(string $key, ?string $configured): ?string
    {
        $key = "SharePointDocs::{$key}";

        return $configured !== null && Lang::has($key, Language::DEFAULT) && __($key, [], Language::DEFAULT) === $configured
            ? __($key)
            : $configured;
    }
}
