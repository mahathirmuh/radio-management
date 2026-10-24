<?php

namespace Modules\SnipeIt\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Everything Snipe-IT says about one user, asked at once (the questions go out together): the user, the assets, licenses
 * and accessories checked out to them, and the history: what was checked out to or in from them, and what was done to
 * the user itself (the activity log, newest first). Kept like every answer (Answers::seconds()); $fresh asks again.
 * Snipe-IT 6.2 has no answer for the consumables of a user: only how many there are.
 */
final class UserDetail
{
    /** How many entries of the history are asked; Snipe-IT's own page has the rest. */
    public const HISTORY = 100;

    public function __construct(private readonly SnipeItClient $client)
    {
    }

    /**
     * @return array{user: array<string, mixed>, assets: list<array<string, mixed>>, licenses: list<array<string, mixed>>,
     *     accessories: list<array<string, mixed>>, history: array{total: int, rows: list<array<string, mixed>>},
     *     changes: array{total: int, rows: list<array<string, mixed>>}, at: string}
     *     history: what was checked out to or in from the user; changes: what was done to the user itself
     *
     * @throws SnipeItUnavailable NOT_FOUND when Snipe-IT has no such user; another reason when it gave no answer
     */
    public function get(int $id, bool $fresh = false): array
    {
        $key = 'snipe-it-user:' . $id;
        if ($fresh) {
            Cache::forget($key);
        }

        return Answers::seconds() === 0 ? $this->ask($id) : Cache::remember($key, Answers::seconds(), fn () => $this->ask($id));
    }

    private function ask(int $id): array
    {
        $newest = ['limit' => self::HISTORY, 'sort' => 'created_at', 'order' => 'desc'];
        $answers = $this->client->many([
            'user' => ["users/{$id}", []],
            'assets' => ["users/{$id}/assets", []],
            'licenses' => ["users/{$id}/licenses", []],
            'accessories' => ["users/{$id}/accessories", []],
            'history' => ['reports/activity', ['target_type' => 'user', 'target_id' => $id] + $newest],
            'changes' => ['reports/activity', ['item_type' => 'user', 'item_id' => $id] + $newest],
        ]);
        if (! is_array($answers['user']) || ! is_numeric($answers['user']['id'] ?? null)) {
            // No user: asked once more on its own, which says why (not there, not reached, refused).
            $this->client->get("users/{$id}");
            throw new SnipeItUnavailable(SnipeItUnavailable::NOT_FOUND, 404);
        }

        $rows = fn (?array $answer) => array_values(array_filter(is_array($answer['rows'] ?? null) ? $answer['rows'] : [], 'is_array'));
        $log = fn (?array $answer) => [
            'total' => is_numeric($answer['total'] ?? null) ? (int) $answer['total'] : count($rows($answer)),
            'rows' => $rows($answer),
        ];

        return [
            'user' => $answers['user'],
            'assets' => $rows($answers['assets']),
            'licenses' => $rows($answers['licenses']),
            'accessories' => $rows($answers['accessories']),
            'history' => $log($answers['history']),
            'changes' => $log($answers['changes']),
            'at' => now()->format('d-m-Y H:i'),
        ];
    }
}
