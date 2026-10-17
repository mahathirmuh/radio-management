<?php

namespace Modules\SharePointDocs\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\SharePointDocs\Models\EditRequest;

/**
 * The requests to change a cell of a workbook, as the page "Persetujuan Edit" and the API look them up: every word that is
 * searched for may be in any of the columns that are shown; the counts by status are of what matches the words, whatever
 * status is shown; newest first.
 */
final class EditRequestQuery
{
    /** @return array<int, string> the words searched for (at most 100 characters of them) */
    public static function terms(string $search): array
    {
        return preg_split('/\s+/u', mb_substr(trim($search), 0, 100), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** The requests that have every word somewhere in the columns that are shown. */
    public static function matching(array $terms): Builder
    {
        $query = EditRequest::query();
        foreach ($terms as $term) {
            // (SQL Server's LIKE has no backslash: its own wildcards are made plain by putting them in brackets.)
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
            $query->where(fn ($q) => $q
                ->where('cell', 'like', $like)->orWhere('sheet', 'like', $like)->orWhere('column_title', 'like', $like)
                ->orWhere('old_text', 'like', $like)->orWhere('new_value', 'like', $like)
                ->orWhere('requested_by_name', 'like', $like)->orWhere('decided_by_name', 'like', $like)
                ->orWhere('anchor_value', 'like', $like));
        }

        return $query;
    }

    /**
     * How many requests of each status match the words.
     *
     * @return array<string, int> by every status of EditRequest::LABELS
     */
    public static function counts(array $terms): array
    {
        $counts = array_fill_keys(array_keys(EditRequest::LABELS), 0);
        foreach (self::matching($terms)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $name => $total) {
            $counts[$name] = (int) $total;
        }

        return $counts;
    }

    /** One page of the requests that match the words and (when it is one) the status, newest first. */
    public static function page(array $terms, string $status, int $perPage, int $page): LengthAwarePaginator
    {
        return self::matching($terms)
            ->when(array_key_exists($status, EditRequest::LABELS), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }
}
