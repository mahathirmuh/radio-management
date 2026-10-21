<?php

namespace Modules\SnipeIt\Services;

use Illuminate\Support\Facades\Cache;

/**
 * What is in Snipe-IT at a glance: how many rows each list has, the assets by status and by category, and Snipe-IT's
 * version. Asked all at once (the questions go out together) and kept for SnipeIt::minutes(), like every answer here; the
 * button "Refresh" asks again.
 */
final class Summary
{
    public const CACHE_KEY = 'snipe-it-summary';

    public function __construct(private readonly SnipeItClient $client)
    {
    }

    /**
     * @return array{totals: array<string, int|null>, statuses: list<array{id: int, name: string, type: string, assets: int}>,
     *     categories: list<array{id: int, name: string, assets: int}>, version: ?string, at: string}
     *
     * @throws SnipeItUnavailable when Snipe-IT is not set up, or answered none of the questions
     */
    public function get(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, Answers::seconds(), fn () => $this->ask());
    }

    private function ask(): array
    {
        $questions = [];
        foreach (Lists::GROUPS as $lists) {
            foreach ($lists as $list) {
                $questions[$list] = [Lists::get($list)['endpoint'], ['limit' => 1]];
            }
        }
        $questions['_statuses'] = ['statuslabels', ['limit' => 100, 'sort' => 'name', 'order' => 'asc']];
        $questions['_categories'] = ['categories', ['limit' => 500, 'category_type' => 'asset', 'sort' => 'assets_count', 'order' => 'desc']];
        $questions['_version'] = ['version', []];

        $answers = $this->client->many($questions);
        if (array_filter($answers) === []) {
            // Nothing came: one question more, which says why (not reached, the token refused, an error).
            $this->client->get('hardware', ['limit' => 1]);
        }

        $totals = [];
        foreach (Lists::GROUPS as $lists) {
            foreach ($lists as $list) {
                $total = $answers[$list]['total'] ?? null;
                $totals[$list] = is_numeric($total) ? (int) $total : null;
            }
        }

        $statuses = [];
        foreach ($answers['_statuses']['rows'] ?? [] as $row) {
            if (is_array($row) && is_numeric($row['id'] ?? null)) {
                $statuses[] = [
                    'id' => (int) $row['id'],
                    'name' => Rows::text($row['name'] ?? null) ?? '#' . $row['id'],
                    'type' => strtolower((string) Rows::text($row['type'] ?? null)),
                    'assets' => (int) ($row['assets_count'] ?? 0),
                ];
            }
        }

        $categories = [];
        foreach ($answers['_categories']['rows'] ?? [] as $row) {
            if (is_array($row) && is_numeric($row['id'] ?? null) && (int) ($row['assets_count'] ?? 0) > 0) {
                $categories[] = [
                    'id' => (int) $row['id'],
                    'name' => Rows::text($row['name'] ?? null) ?? '#' . $row['id'],
                    'assets' => (int) $row['assets_count'],
                ];
            }
        }
        usort($categories, fn ($a, $b) => [$b['assets'], $a['name']] <=> [$a['assets'], $b['name']]);

        return [
            'totals' => $totals,
            'statuses' => $statuses,
            'categories' => $categories,
            'version' => Rows::text($answers['_version']['version'] ?? null),
            'at' => now()->format('d-m-Y H:i'),
        ];
    }
}
