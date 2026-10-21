<?php

namespace Modules\RadioManagement\Services;

/**
 * The name to show of a value the register keeps in Indonesian (an archive reason, an accessory, the kind of a line of
 * the history), in the language of the page: RadioManagement::common.<group>. A value without a name there (one added
 * to the configuration later) shows as it is. What is stored is never translated.
 */
final class RadioWords
{
    public static function of(string $group, ?string $value, ?string $locale = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $names = __('RadioManagement::common.' . $group, [], $locale);

        return is_array($names) && isset($names[$value]) ? (string) $names[$value] : $value;
    }

    /** @return array<string, string> value => name, for a page's script to look up */
    public static function all(string $group): array
    {
        $names = __('RadioManagement::common.' . $group);

        return is_array($names) ? $names : [];
    }
}
