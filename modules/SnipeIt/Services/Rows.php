<?php

namespace Modules\SnipeIt\Services;

use App\Services\Language;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * A row of the API as the page shows it: only the columns of its list (Lists), each as text (t), a second line (s) and the
 * colour of its badge (tone). Snipe-IT writes its texts HTML-escaped ("O&#039;Neil"): they are read back to plain text here,
 * and the page escapes them again where it writes them.
 */
final class Rows
{
    /** The colour of a status (Snipe-IT's status_meta, or the type of a status label) and of an action of the activity log. */
    private const TONES = [
        'deployed' => 'blue',
        'deployable' => 'green',
        'pending' => 'yellow',
        'undeployable' => 'red',
        'archived' => 'gray',
        'checkout' => 'blue',
        'checkin from' => 'purple',
        'create new' => 'green',
        'update' => 'orange',
        'delete' => 'red',
        'audit' => 'yellow',
    ];

    /** Snipe-IT's page of the item an entry of the activity log is about, by the item's type. */
    private const ITEM_PAGES = [
        'asset' => 'hardware',
        'user' => 'users',
        'license' => 'licenses',
        'accessory' => 'accessories',
        'consumable' => 'consumables',
        'component' => 'components',
        'location' => 'locations',
    ];

    /** The longest text of a cell; Snipe-IT's own page has the rest. */
    private const LONGEST = 200;

    /** The icon of an asset without a picture, by its category. */
    private const ICONS = [
        '/\b(ht|radio|repeater|rig)\b/i' => 'radio',
        '/notebook|laptop/i' => 'device-laptop',
        '/\bpc\b|desktop|monitor/i' => 'device-desktop',
        '/cctv|camera|nvr/i' => 'device-cctv',
        '/printer/i' => 'printer',
        '/switch|router|access ?point|network/i' => 'router',
        '/tablet/i' => 'device-tablet',
        '/phone|mobile/i' => 'device-mobile',
        '/\bsim/i' => 'device-sim',
        '/\bups\b|battery/i' => 'battery-charging',
        '/headset/i' => 'headset',
    ];

    /**
     * @param  array<string, mixed>  $list  Lists::get()
     * @param  array<string, mixed>  $row  a row of the API
     * @param  bool  $users  whoever looks may open the detail of a user here (else an entry about a user leads to Snipe-IT)
     * @return array{id: int|null, link: string|null, inside: bool, image?: ?string, icon?: string, cells: array<string, array{t: ?string, s: ?string, tone: ?string}>}
     *     link: the page of the row; inside: it is a page of this application (the detail of an asset or a user), not
     *     Snipe-IT's; image and icon (a list with pictures): Snipe-IT's picture of the row, and the icon shown without one
     */
    public static function show(array $list, array $row, ?string $web, bool $users = false): array
    {
        $cells = [];
        foreach ($list['columns'] as $key => $column) {
            $cells[$key] = self::cell($column, $row);
        }
        [$link, $inside] = self::link($list, $row, $web, $users);

        return [
            'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : null,
            'link' => $link,
            'inside' => $inside,
            ...(isset($list['image']) ? [
                'image' => self::image(data_get($row, $list['image'])),
                'icon' => self::icon(self::text(data_get($row, 'category.name'))),
            ] : []),
            'cells' => $cells,
        ];
    }

    /** A picture of Snipe-IT's, only by a web address (the browser loads it from Snipe-IT). */
    public static function image(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://[^\s"\'<>]+$#i', $url) ? $url : null;
    }

    /** The icon of an asset (a Tabler icon, without "ti-"), by the name of its category. */
    public static function icon(?string $category): string
    {
        foreach (self::ICONS as $pattern => $icon) {
            if (preg_match($pattern, (string) $category)) {
                return $icon;
            }
        }

        return 'box';
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $row
     * @return array{t: ?string, s: ?string, tone: ?string}
     */
    public static function cell(array $column, array $row): array
    {
        $value = data_get($row, $column['path']);
        $type = $column['type'] ?? 'text';
        $tone = null;

        if ($type === 'open') {
            // The button to the row's page: no text of its own.
            return ['t' => null, 's' => null, 'tone' => null];
        }
        if ($type === 'bool') {
            $yes = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($yes === null) {
                return ['t' => null, 's' => null, 'tone' => null];
            }
            $text = self::word($column['words'] ?? 'yes_no', $yes ? 'yes' : 'no');
            $tone = $yes ? 'green' : 'gray';
        } elseif ($type === 'changes') {
            $text = app(Names::class)->line($value);
        } elseif ($type === 'number') {
            $text = is_numeric($value) ? Language::number(+$value, floor(+$value) == +$value ? 0 : 2) : self::text($value);
        } elseif ($type === 'date') {
            $text = self::date($value);
        } else {
            $text = self::text($value);
            if (isset($column['words']) && $text !== null) {
                $text = self::word($column['words'], Str::lower($text)) ?? $text;
            }
        }

        if ($type === 'badge' && $text !== null) {
            $key = Str::lower((string) self::text(data_get($row, $column['tone'] ?? $column['path'])));
            $tone = self::TONES[$key] ?? 'gray';
        }

        $sub = isset($column['sub']) ? self::text(data_get($row, $column['sub'])) : null;
        if ($sub !== null && isset($column['subWords'])) {
            $sub = self::word($column['subWords'], Str::lower($sub)) ?? $sub;
        }

        return ['t' => $text, 's' => $sub, 'tone' => $tone];
    }

    /**
     * Plain text of a value: a text of Snipe-IT unescaped, without markup, on one line; a name for a thing ({id, name}); the
     * names of a list of things. Nothing (null) for what has no text.
     */
    public static function text(mixed $value): ?string
    {
        if (is_bool($value)) {
            return self::word('yes_no', $value ? 'yes' : 'no');
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            if (array_key_exists('name', $value)) {
                return self::text($value['name']);
            }
            if (array_key_exists('datetime', $value) || array_key_exists('date', $value)) {
                return self::date($value);
            }
            if (array_is_list($value)) {
                $names = array_values(array_filter(array_map(fn ($item) => self::text($item), $value), fn ($name) => $name !== null));

                return $names === [] ? null : Str::limit(implode(', ', $names), self::LONGEST);
            }

            return null;
        }
        if (! is_string($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : Str::limit($text, self::LONGEST);
    }

    /** A moment of Snipe-IT ({datetime, formatted}) as day and time, a day ({date, formatted}) as the day: as the pages write them. */
    public static function date(mixed $value): ?string
    {
        if (! is_array($value)) {
            return self::text($value);
        }
        $moment = $value['datetime'] ?? null;
        $day = $value['date'] ?? null;
        try {
            if (is_string($moment) && $moment !== '') {
                return Carbon::parse($moment)->format('d-m-Y H:i');
            }
            if (is_string($day) && $day !== '') {
                return Carbon::parse($day)->format('d-m-Y');
            }
        } catch (Throwable) {
            return self::text($value['formatted'] ?? null);
        }

        return null;
    }

    /**
     * The page of the row: a detail here (a list's 'detail': an asset's, or a user's; an entry of the activity log about an
     * asset, or about a user when whoever looks may open it), else the row's page in Snipe-IT itself when the list has one
     * and Snipe-IT's address is known.
     *
     * @return array{0: ?string, 1: bool} the address, and whether it is a page of this application
     */
    private static function link(array $list, array $row, ?string $web, bool $users): array
    {
        $here = fn (string $route, mixed $id) => is_numeric($id) && (int) $id > 0 ? [route($route, (int) $id), true] : [null, false];
        if (isset($list['detail'])) {
            return $here($list['detailRoute'] ?? 'snipe-it.assets.show', data_get($row, $list['detail']));
        }
        $page = $list['web'] ?? null;
        if ($page === '{item}') {
            $type = Str::lower((string) data_get($row, 'item.type'));
            $id = data_get($row, 'item.id');
            if ($type === 'asset' || ($type === 'user' && $users)) {
                return $here($type === 'asset' ? 'snipe-it.assets.show' : 'snipe-it.users.show', $id);
            }

            return $web !== null && isset(self::ITEM_PAGES[$type]) && is_numeric($id) ? [$web . '/' . self::ITEM_PAGES[$type] . '/' . (int) $id, false] : [null, false];
        }
        $id = data_get($row, 'id');
        if ($page === null || $web === null || ! is_numeric($id)) {
            return [null, false];
        }

        return [$web . '/' . str_replace('{id}', (string) (int) $id, $page), false];
    }

    /** A word of the page's (SnipeIt::snipe.<group>.<key>), or null when the group has none for $key. */
    private static function word(string $group, string $key): ?string
    {
        $words = __('SnipeIt::snipe.' . $group);

        return is_array($words) && isset($words[$key]) && is_string($words[$key]) ? $words[$key] : null;
    }

    /** The rows of a whole list (Snipe-IT gave all at once) that hold $search in one of their texts. */
    public static function matching(array $list, array $rows, string $search): array
    {
        if ($search === '') {
            return $rows;
        }
        $needle = Str::lower($search);

        return array_values(array_filter($rows, function (array $row) use ($list, $needle) {
            foreach ($list['columns'] as $column) {
                $cell = self::cell($column, $row);
                foreach (Arr::only($cell, ['t', 's']) as $text) {
                    if ($text !== null && str_contains(Str::lower($text), $needle)) {
                        return true;
                    }
                }
            }

            return false;
        }));
    }
}
