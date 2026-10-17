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
        ], ['archive_reason' => 'Alasan', 'archived_at' => 'Tanggal', 'note' => 'Catatan']];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} rules, and the names of the fields in messages */
    public static function history(): array
    {
        return [[
            'action' => ['required', Rule::in(config('radio.history_actions'))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:2000'],
            'document_no' => ['nullable', 'string', 'max:100'],
        ], ['action' => 'Jenis', 'event_date' => 'Tanggal', 'description' => 'Keterangan', 'document_no' => 'No. Dokumen']];
    }
}
