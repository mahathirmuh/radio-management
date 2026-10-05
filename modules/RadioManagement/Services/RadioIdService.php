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
