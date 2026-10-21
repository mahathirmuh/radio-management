<?php

namespace Modules\SnipeIt\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Everything Snipe-IT says about one asset, asked at once (the questions go out together): the asset, its history (the
 * activity log of the asset, newest first), its maintenances, the licenses checked out to it, and the assets checked out
 * to it. Kept like every answer (Answers::seconds()); $fresh asks again. Snipe-IT 6.2 has no answer for the components or
 * the files of an asset.
 */
final class AssetDetail
{
    /** How many entries of the history are shown; Snipe-IT's own page has the rest. */
    public const HISTORY = 100;

    public function __construct(private readonly SnipeItClient $client)
    {
    }

    /**
     * @return array{asset: array<string, mixed>, history: array{total: int, rows: list<array<string, mixed>>},
     *     maintenances: list<array<string, mixed>>, licenses: list<array<string, mixed>>, attached: list<array<string, mixed>>, at: string}
     *
     * @throws SnipeItUnavailable NOT_FOUND when Snipe-IT has no such asset; another reason when it gave no answer
     */
    public function get(int $id, bool $fresh = false): array
    {
        $key = 'snipe-it-asset:' . $id;
        if ($fresh) {
            Cache::forget($key);
        }

        return Answers::seconds() === 0 ? $this->ask($id) : Cache::remember($key, Answers::seconds(), fn () => $this->ask($id));
    }

    private function ask(int $id): array
    {
        $answers = $this->client->many([
            'asset' => ["hardware/{$id}", []],
            'history' => ['reports/activity', ['item_type' => 'asset', 'item_id' => $id, 'limit' => self::HISTORY, 'sort' => 'created_at', 'order' => 'desc']],
            'maintenances' => ['maintenances', ['asset_id' => $id, 'limit' => 50, 'sort' => 'start_date', 'order' => 'desc']],
            'licenses' => ["hardware/{$id}/licenses", []],
            'attached' => ['hardware', ['assigned_to' => $id, 'assigned_type' => 'App\\Models\\Asset', 'limit' => 50]],
        ]);
        if (! is_array($answers['asset']) || ! is_numeric($answers['asset']['id'] ?? null)) {
            // No asset: asked once more on its own, which says why (not there, not reached, refused).
            $this->client->get("hardware/{$id}");
            throw new SnipeItUnavailable(SnipeItUnavailable::NOT_FOUND, 404);
        }

        $rows = fn (?array $answer) => array_values(array_filter(is_array($answer['rows'] ?? null) ? $answer['rows'] : [], 'is_array'));

        return [
            'asset' => $answers['asset'],
            'history' => [
                'total' => is_numeric($answers['history']['total'] ?? null) ? (int) $answers['history']['total'] : count($rows($answers['history'])),
                'rows' => $rows($answers['history']),
            ],
            'maintenances' => $rows($answers['maintenances']),
            'licenses' => $rows($answers['licenses']),
            'attached' => $rows($answers['attached']),
            'at' => now()->format('d-m-Y H:i'),
        ];
    }
}
