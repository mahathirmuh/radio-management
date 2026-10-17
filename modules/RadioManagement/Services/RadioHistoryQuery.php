<?php

namespace Modules\RadioManagement\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Modules\RadioManagement\Models\RadioHistory;

/**
 * The lines of "Riwayat Radio" across all radios, newest first, as the page and the API filter them.
 */
class RadioHistoryQuery
{
    /** Actions written by the system in addition to the manual ones in config. */
    public const SYSTEM_ACTIONS = ['Dibuat', 'Ubah Data', 'Ubah Status', 'Arsip', 'Dipulihkan', 'Impor', 'Dihapus'];

    /** Every kind of line there is: the manual ones (config) and the system's, sorted. */
    public static function actions(): array
    {
        return collect(config('radio.history_actions'))->merge(self::SYSTEM_ACTIONS)->unique()->sort()->values()->all();
    }

    /**
     * @param string $search in the description, the document number, and the SN, user and Radio ID of the radio
     * @param string[] $actions only these kinds of line
     */
    public function build(string $search = '', array $actions = [], ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): Builder
    {
        return RadioHistory::query()
            ->with(['radio', 'user'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    Like::any($q, ['radio_histories.description', 'radio_histories.document_no'], $search);
                    $q->orWhereHas('radio', function ($radio) use ($search) {
                        $radio->withTrashed()->where(function ($r) use ($search) {
                            Like::any($r, ['radios.serial_number', 'radios.user_name'], $search);
                            if (ctype_digit($search) && strlen($search) <= 9) {
                                $r->orWhere('dmr_id', (int) $search)->orWhere('previous_dmr_id', (int) $search);
                            }
                        });
                    });
                });
            })
            ->when($actions !== [], fn ($query) => $query->whereIn('action', $actions))
            ->when($from, fn ($query) => $query->where('event_date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($query) => $query->where('event_date', '<=', $to->format('Y-m-d')))
            ->orderByDesc('event_date')
            ->orderByDesc('id');
    }
}
