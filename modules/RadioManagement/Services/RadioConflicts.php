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
            return "Radio ID {$id} tidak bisa dipakai: MDC Code-nya (" . RadioIdService::mdc($id)
                . ') mengandung huruf F. Ganti dengan ID lain.';
        }

        $other = Radio::query()->active()->where('dmr_id', $id)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if ($other) {
            return "Radio ID {$id} sudah dipakai radio lain ({$other->display_name}).";
        }

        $holder = Radio::query()->active()->whereNull('dmr_id')->where('previous_dmr_id', $id)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if ($holder) {
            return "Radio ID {$id} dicadangkan untuk {$holder->display_name}, yang kemungkinan masih memakai ID itu. "
                . 'Cek radio tersebut dulu.';
        }

        return null;
    }

    public function serial(string $serial, ?int $exceptRadioId = null): ?string
    {
        $other = Radio::query()->where('serial_number', $serial)
            ->when($exceptRadioId, fn ($query) => $query->where('id', '!=', $exceptRadioId))
            ->first();
        if (! $other) {
            return null;
        }
        $state = $other->isArchived() ? ' (diarsipkan, pulihkan saja radio tersebut)' : '';

        return "SN {$serial} sudah terdaftar pada {$other->display_name}{$state}.";
    }
}
