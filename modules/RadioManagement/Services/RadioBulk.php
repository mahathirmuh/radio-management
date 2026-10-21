<?php

namespace Modules\RadioManagement\Services;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Models\Radio;

/**
 * Several radios at once, from the register list: the radios ticked one by one, or (to verify, and to export) every radio
 * the list's filter shows. Each radio goes through RadioService as one radio would (its history, the audit trail, the
 * write lock); what cannot be done to one is said for that one, and the others go on.
 */
class RadioBulk
{
    /** Archiving and deleting: radios ticked one by one only, at most this many at a time. */
    public const MAX_TICKED = 100;

    /** Verifying: at most this many at a time (every radio of a filter may be chosen). */
    public const MAX_ALL = 1000;

    public function __construct(private RadioService $radios, private RadioFilter $filter)
    {
    }

    /**
     * The radios an action is for, in the order they were chosen: the ids ticked ("ids[]"), or with all=1 (only when
     * $everyFiltered) every radio the list's filter shows.
     *
     * @return Collection<int, Radio>
     *
     * @throws ValidationException nothing chosen, too many, or "every radio of the filter" for an action that does not take it
     */
    public function chosen(Request $request, bool $everyFiltered, int $max): Collection
    {
        if ($request->boolean('all')) {
            if (! $everyFiltered) {
                throw ValidationException::withMessages(['ids' => __('RadioManagement::register.messages.one_by_one')]);
            }
            $ids = $this->filter->query($request)->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $ids = RadioFilter::ids($request);
        }

        if ($ids === []) {
            throw ValidationException::withMessages(['ids' => __('RadioManagement::register.messages.none_chosen')]);
        }
        if (count($ids) > $max) {
            throw ValidationException::withMessages(['ids' => __('RadioManagement::register.messages.too_many', ['count' => count($ids), 'max' => $max])]);
        }

        $position = array_flip($ids);

        return Radio::query()->whereIntegerInRaw('id', $ids)->get()
            ->sortBy(fn (Radio $radio) => $position[$radio->id])->values();
    }

    /**
     * A "Verifikasi" line in the history of each radio, which also moves its date of verification forward (RadioService
     * ::addHistory). An archived radio is left as it is.
     *
     * @return array{done: int, skipped: list<array{radio: string, reason: string}>}
     */
    public function verify(Collection $radios, string $date, string $note, ?int $userId): array
    {
        return $this->each($radios, function (Radio $radio) use ($date, $note, $userId): ?string {
            if ($radio->isArchived()) {
                return __('RadioManagement::register.messages.skipped_archived');
            }
            $this->radios->addHistory($radio, ['action' => 'Verifikasi', 'event_date' => $date, 'description' => $note], $userId);

            return null;
        });
    }

    /** @return array{done: int, skipped: list<array{radio: string, reason: string}>} */
    public function archive(Collection $radios, string $reason, string $date, ?string $note, ?int $userId): array
    {
        return $this->each($radios, function (Radio $radio) use ($reason, $date, $note, $userId): ?string {
            $this->radios->archive($radio, $reason, $date, $note, $userId);

            return null;
        });
    }

    /** @return array{done: int, skipped: list<array{radio: string, reason: string}>} */
    public function delete(Collection $radios, ?int $userId): array
    {
        return $this->each($radios, function (Radio $radio) use ($userId): ?string {
            $this->radios->delete($radio, $userId);

            return null;
        });
    }

    /** How a radio is named in what is said back: "ID 11 · 867TZB0011", or its SN, or its number. */
    public static function label(Radio $radio): string
    {
        $parts = array_filter([$radio->dmr_id ? 'ID ' . $radio->dmr_id : null, $radio->serial_number ?: null]);

        return $parts === [] ? '#' . $radio->id : implode(' · ', $parts);
    }

    /**
     * Runs $one for each radio: null when it was done, else the reason it was not. A refusal of the register (the radio
     * changed meanwhile, it is archived already) is that radio's reason; a register that stays busy (an import running)
     * stops the rest, which are said to be left out for that reason rather than each waiting its turn in vain.
     *
     * @param Closure(Radio): ?string $one
     * @return array{done: int, skipped: list<array{radio: string, reason: string}>}
     */
    private function each(Collection $radios, Closure $one): array
    {
        $done = 0;
        $skipped = [];
        $busy = false;
        foreach ($radios as $radio) {
            if ($busy) {
                $skipped[] = ['radio' => self::label($radio), 'reason' => __('RadioManagement::register.messages.busy_reason')];

                continue;
            }
            try {
                $reason = $one($radio);
            } catch (ValidationException $e) {
                $reason = rtrim((string) (collect($e->errors())->flatten()->first() ?? __('RadioManagement::register.messages.refused')), '.');
            } catch (LockTimeoutException) {
                $busy = true;
                $reason = __('RadioManagement::register.messages.busy_reason');
            }
            if ($reason === null) {
                $done++;
            } else {
                $skipped[] = ['radio' => self::label($radio), 'reason' => $reason];
            }
        }

        return ['done' => $done, 'skipped' => $skipped];
    }
}
