<?php

namespace Modules\RadioManagement\Services;

/**
 * "Contains this text" search where the typed text is taken literally.
 *
 * In a LIKE pattern % and _ are wildcards, and on SQL Server so is [ ]: searching "[NEW]"
 * would match every row containing N, E or W. The explicit escape character works the same
 * on every database driver.
 */
class Like
{
    private const ESCAPE = '!';

    public static function pattern(string $term): string
    {
        return '%' . str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $term) . '%';
    }

    /**
     * Adds "column contains term" for every column, OR-ed together. Call it first inside a
     * nested where() group. Column names must come from code, never from the request.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param string[] $columns
     */
    public static function any($query, array $columns, string $term): void
    {
        $pattern = self::pattern($term);
        foreach ($columns as $column) {
            $query->orWhereRaw("{$column} like ? escape '" . self::ESCAPE . "'", [$pattern]);
        }
    }
}
