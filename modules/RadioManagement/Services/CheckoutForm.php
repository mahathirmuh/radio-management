<?php

namespace Modules\RadioManagement\Services;

use Modules\RadioManagement\Models\Radio;

/**
 * What the "IT Equipment Checkout Form" (button "Cetak") fills in for a radio, as data: the API hands it to an
 * application that prints the form its own way. The keys of `fields` are the names of the boxes of the printed form
 * (`data-field` in resources radio/checkout-form); a box that the register does not fill is not in it.
 *
 * No signature: the one of the person at ICT is put on the form only when that person opens it on the page.
 */
class CheckoutForm
{
    public const NUMBER = 'MTI-HRM-ICT-FRM-001';

    public const REVISION = 'Rev.000';

    public const TITLE = 'IT EQUIPMENT CHECKOUT FORM';

    /** @return array<string, mixed> */
    public function for(Radio $radio): array
    {
        $radio->loadMissing(['radioType', 'department']);
        $category = $radio->radioType?->category;
        $today = now()->format('d-m-Y');

        return [
            'form' => ['number' => self::NUMBER, 'revision' => self::REVISION, 'title' => self::TITLE],
            /** "HT" or "RIG": the kind of radio. The other one is struck out on the form ("*) HT / RIG"). */
            'category' => $category,
            'struck' => match ($category) {
                'HT' => 'RIG',
                'RIG' => 'HT',
                default => null,
            },
            'date' => $today,
            'fields' => array_filter([
                // REQUESTOR (filled by ICT)
                'requestor_name' => $radio->user_name,
                'requestor_department' => $radio->department?->name,
                // HT / RIG
                'radio_id' => $radio->dmr_id === null ? null : (string) $radio->dmr_id,
                'radio_type' => $radio->radioType?->name,
                'radio_serial' => $radio->serial_number,
                // RESPONSIBLE USER
                'user_name' => $radio->user_name,
                'user_department' => $radio->department?->name,
                // PROCESSED BY (ICT): the date; the name and the signature are of whoever processes it.
                'ict_date' => $today,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }
}
