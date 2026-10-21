<?php

namespace Modules\RadioManagement\Services;

use Modules\RadioManagement\Models\Radio;

/**
 * "Is this Radio ID / SN free?" Used by the form validation and repeated inside the
 * write lock of RadioService, so two people saving at the same moment cannot both win.
 * Each method returns the message to show, or null when there is no conflict.
 */
class RadioConflicts
{
    /** SN the way it is stored: upper case, no whitespace. */
    public static function normalizeSerial(?string $serial): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', (string) $serial));
    }

    public function radioId(int $id, ?int $exceptRadioId = null): ?string
    {
        if (RadioIdService::hasHexF($id)) {
            return __('RadioManagement::register.messages.id_hex_f', ['id' => $id, 'mdc' => RadioIdService::mdc($id)]);
        }

        $other = Radio::query()->active()->where('dmr_id', $id)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if ($other) {
            return __('RadioManagement::register.messages.id_taken', ['id' => $id, 'radio' => $other->display_name]);
        }

        $holder = Radio::query()->active()->whereNull('dmr_id')->where('previous_dmr_id', $id)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if ($holder) {
            return __('RadioManagement::register.messages.id_reserved', ['id' => $id, 'radio' => $holder->display_name]);
        }

        return null;
    }

    /**
     * The active radio that holds a Radio ID ("taken"), or that last held it and has none now ("reserved": it probably
     * still carries it); null when the ID is nobody's. The radio $exceptRadioId (the one being edited) is left out.
     *
     * @return array{radio: Radio, kind: string}|null
     */
    public function holderOf(int $id, ?int $exceptRadioId = null): ?array
    {
        $taken = Radio::query()->active()->with('department')->where('dmr_id', $id)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if ($taken) {
            return ['radio' => $taken, 'kind' => 'taken'];
        }

        $reserved = Radio::query()->active()->with('department')->whereNull('dmr_id')->where('previous_dmr_id', $id)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();

        return $reserved ? ['radio' => $reserved, 'kind' => 'reserved'] : null;
    }

    public function serial(string $serial, ?int $exceptRadioId = null): ?string
    {
        $other = Radio::query()->where('serial_number', $serial)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if (! $other) {
            return null;
        }
        return __('RadioManagement::register.messages.' . ($other->isArchived() ? 'sn_taken_archived' : 'sn_taken'), ['sn' => $serial, 'radio' => $other->display_name]);
    }
}
