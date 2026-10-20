<?php

namespace Modules\RadioManagement\Services;

use Modules\RadioManagement\Models\Radio;

/**
 * Radio ID pool rules shared by the form, the checks and the importer.
 */
class RadioIdService
{
    /** MDC code = Radio ID in hexadecimal (same as Excel's DEC2HEX). */
    public static function mdc(?int $id): ?string
    {
        return $id ? strtoupper(dechex($id)) : null;
    }

    /** "F" is the MDC wildcard digit, so such IDs are never assigned. */
    public static function hasHexF(?int $id): bool
    {
        return $id !== null && $id > 0 && str_contains(strtoupper(dechex($id)), 'F');
    }

    /** IDs held by active radios (archived radios have released theirs). */
    public function usedIds(?int $exceptRadioId = null): array
    {
        return Radio::query()
            ->active()
            ->whereNotNull('dmr_id')
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->toBase() // plain values: plucking a cast column through Eloquent builds a model per row
            ->pluck('dmr_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Last known IDs of active radios that currently have no ID (e.g. the Excel cell
     * was blank). The radio probably still carries it, so it is not handed out.
     */
    public function reservedIds(?int $exceptRadioId = null): array
    {
        return Radio::query()
            ->active()
            ->whereNull('dmr_id')
            ->whereNotNull('previous_dmr_id')
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->toBase()
            ->pluck('previous_dmr_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Used or reserved by another radio. */
    public function isTaken(int $id, ?int $exceptRadioId = null): bool
    {
        return in_array($id, $this->usedIds($exceptRadioId), true)
            || in_array($id, $this->reservedIds($exceptRadioId), true);
    }

    /** Smallest ID >= $id whose hexadecimal form has no "F" (751 = 2EF -> 768 = 300). */
    public static function nextWithoutHexF(int $id): int
    {
        while (($position = strpos($hex = dechex($id), 'f')) !== false) {
            // The leftmost F and every digit after it roll over: 2EF -> 2F0 -> 300.
            $step = 16 ** (strlen($hex) - $position);
            $id = (intdiv($id, $step) + 1) * $step;
        }

        return $id;
    }

    /**
     * What the Radio ID picker of the form shows for one ID: whether it can be used and why not (status ok, hex_f, taken
     * or reserved), who holds it, the first ID above it that can be used instead, the run of IDs with F that is skipped,
     * the IDs around it, and the lowest free IDs from there. The radio $exceptRadioId (the one being edited) holds
     * nothing here: its own ID is free for it.
     *
     * @return array<string, mixed>
     */
    public function check(int $id, ?int $exceptRadioId = null): array
    {
        $taken = array_flip(array_merge($this->usedIds($exceptRadioId), $this->reservedIds($exceptRadioId)));
        $max = (int) config('radio.id_max');
        $state = fn (int $n) => self::hasHexF($n) ? 'hex_f' : (isset($taken[$n]) ? 'used' : 'free');
        $cell = fn (int $n, bool $selected = false) => ['id' => $n, 'mdc' => self::mdc($n), 'state' => $state($n), 'selected' => $selected];

        $holder = app(RadioConflicts::class)->holderOf($id, $exceptRadioId);
        $status = self::hasHexF($id) ? 'hex_f' : ($holder['kind'] ?? 'ok');

        // The first ID above this one that can be used: past the IDs with F, and past those that are taken.
        $suggested = null;
        if ($status !== 'ok') {
            for ($next = self::nextWithoutHexF($id + 1); $next <= $max; $next = self::nextWithoutHexF($next + 1)) {
                if (! isset($taken[$next])) {
                    $suggested = $next;
                    break;
                }
            }
        }

        // The IDs around it: two below and three above an ID that can be used; else the one below, this one, what lies
        // between it and the suggestion (shortened when there is much), and the suggestion.
        $strip = [];
        if ($status === 'ok') {
            for ($n = $id - 2; $n <= $id + 3; $n++) {
                if ($n >= 1 && $n <= $max) {
                    $strip[] = $cell($n, $n === $id);
                }
            }
        } else {
            if ($id > 1) {
                $strip[] = $cell($id - 1);
            }
            $strip[] = $cell($id, true);
            $end = $suggested ?? min($max + 1, $id + 4);
            $between = $end - $id - 1;
            if ($between > 3) {
                $strip[] = $cell($id + 1);
                $strip[] = ['gap' => $between - 2];
                $strip[] = $cell($end - 1);
            } else {
                for ($n = $id + 1; $n < $end; $n++) {
                    $strip[] = $cell($n);
                }
            }
            if ($suggested !== null) {
                $strip[] = $cell($suggested);
            }
        }

        $radio = $holder['radio'] ?? null;
        $current = $exceptRadioId !== null ? Radio::query()->whereKey($exceptRadioId)->value('dmr_id') : null;
        $own = $current !== null && (int) $current === $id;

        return [
            'id' => $id,
            'mdc' => self::mdc($id),
            'status' => $status,
            // The radio's own ID (an imported one with F, say): it may be kept until the radio is programmed again.
            'own' => $own,
            'holder' => $radio === null ? null : [
                'id' => $radio->id,
                'label' => $radio->display_name,
                'user' => $radio->user_name,
                'department' => $radio->department?->name,
            ],
            'suggested' => $suggested === null ? null : ['id' => $suggested, 'mdc' => self::mdc($suggested)],
            'skipped' => $status === 'hex_f' ? ['from' => $id, 'to' => self::nextWithoutHexF($id) - 1] : null,
            'strip' => $strip,
            'next' => array_map(
                fn (int $n) => ['id' => $n, 'mdc' => self::mdc($n)],
                $this->nextFree(5, $exceptRadioId, ($suggested ?? $id) - 1)
            ),
        ];
    }

    /**
     * Lowest free IDs, skipping used and reserved IDs and any ID whose hex form contains "F".
     *
     * @param int|null $after only IDs above this one (the replacements offered for an ID that cannot be used)
     * @return int[]
     */
    public function nextFree(int $count = 1, ?int $exceptRadioId = null, ?int $after = null): array
    {
        $used = array_flip(array_merge($this->usedIds($exceptRadioId), $this->reservedIds($exceptRadioId)));
        $max = (int) config('radio.id_max');
        $free = [];

        $id = max((int) config('radio.id_start'), ($after ?? 0) + 1);
        while (count($free) < $count && ($id = self::nextWithoutHexF($id)) <= $max) {
            if (! isset($used[$id])) {
                $free[] = $id;
            }
            $id++;
        }

        return $free;
    }
}
