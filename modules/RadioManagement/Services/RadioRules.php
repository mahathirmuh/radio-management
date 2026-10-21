<?php

namespace Modules\RadioManagement\Services;

use Illuminate\Validation\Rule;

/**
 * What is checked when a radio is archived, and when a line is added to its history: the same for the pages and the
 * API. (The radio itself is checked by Http\Requests\RadioRequest.)
 */
class RadioRules
{
    /** @return array{0: array<string, mixed>, 1: array<string, string>} rules, and the names of the fields in messages */
    public static function archive(): array
    {
        return [[
            'archive_reason' => ['required', Rule::in(config('radio.archive_reasons'))],
            'archived_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], self::names(['archive_reason', 'archived_at', 'note'])];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} rules, and the names of the fields in messages */
    public static function history(): array
    {
        return [[
            'action' => ['required', Rule::in(config('radio.history_actions'))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:2000'],
            'document_no' => ['nullable', 'string', 'max:100'],
        ], self::names(['action', 'event_date', 'description', 'document_no'])];
    }

    /** @return array<string, string> the names of the fields in the language of the request */
    private static function names(array $fields): array
    {
        return array_combine($fields, array_map(fn (string $field) => __('RadioManagement::register.fields.' . $field), $fields));
    }
}
