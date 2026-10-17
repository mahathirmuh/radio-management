<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Api\Http\Resources\RadioHistoryResource;
use Modules\RadioManagement\Services\RadioHistoryQuery;

/**
 * @tags History
 */
class HistoryController extends ApiController
{
    /**
     * List the history of all radios
     *
     * The page "Riwayat Radio": handovers, changes of status, programming, verification, archiving, written by the
     * system or by hand. Newest first.
     */
    public function index(Request $request, RadioHistoryQuery $histories): AnonymousResourceCollection
    {
        $this->lists($request, ['action']);
        $request->validate([
            /** In the description and document number, and the SN, user and Radio ID of the radio. */
            'search' => ['nullable', 'string', 'max:100'],
            /** Kinds of line (see "history_kinds" of /references). */
            'action' => ['nullable', 'array'],
            'action.*' => ['string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return RadioHistoryResource::collection($histories->build(
            trim((string) $request->input('search')),
            array_values((array) $request->input('action', [])),
            $request->date('date_from'),
            $request->date('date_to'),
        )->paginate($this->perPage($request)));
    }
}
