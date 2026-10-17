<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\RadioManagement\Services\RadioHistoryQuery;

/**
 * @tags References
 */
class ReferenceController extends ApiController
{
    /**
     * The fixed lists of the register
     *
     * The values a radio, a filter or a history line may take, with the words the pages show for them. Master data
     * (types, companies, departments, channel templates) has endpoints of its own.
     */
    public function index(): JsonResponse
    {
        $pairs = fn (array $labels) => collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return response()->json(['data' => [
            /** Posisi: where the radio is. */
            'placements' => collect(Placement::cases())->map(fn (Placement $p) => ['value' => $p->value, 'label' => $p->label()]),
            /** Kondisi: the state it is in. */
            'conditions' => collect(Condition::cases())->map(fn (Condition $c) => ['value' => $c->value, 'label' => $c->label()]),
            /** Kategori of a radio type. */
            'categories' => $pairs((array) config('radio.categories')),
            'owner_groups' => [['value' => 'mti', 'label' => 'MTI'], ['value' => 'non_mti', 'label' => 'Non MTI']],
            /** Why a radio is archived (POST /radios/{id}/archive). */
            'archive_reasons' => array_values((array) config('radio.archive_reasons')),
            /** The lines a person may add to a radio's history (POST /radios/{id}/history). */
            'history_actions' => array_values((array) config('radio.history_actions')),
            /** Every kind of history line, the system's included (filter of GET /history). */
            'history_kinds' => RadioHistoryQuery::actions(),
            /** What the column "Cek" flags (filter "issue" of GET /radios). */
            'issues' => collect(RadioCheckService::ISSUES)->map(fn (array $issue, string $code) => ['value' => $code, 'label' => $issue['label']])->values(),
            'radio_id' => [
                'min' => 1,
                'max' => (int) config('radio.id_max'),
                /** A Radio ID whose MDC code (its hexadecimal) contains F cannot be used. */
                'hex_f_refused' => true,
            ],
        ]]);
    }
}
