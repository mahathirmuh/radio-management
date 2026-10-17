<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Modules\RadioManagement\Services\RadioSummary;

/**
 * @tags Dashboard
 */
class DashboardController extends ApiController
{
    /**
     * The figures of the dashboard
     *
     * Active radios unless stated otherwise: by Posisi, Kondisi, company and type, what the column "Cek" flags, and the
     * next free Radio IDs.
     */
    public function show(RadioSummary $summary): JsonResponse
    {
        $figures = $summary->figures();

        return response()->json(['data' => [
            'total_active' => (int) $figures['totalActive'],
            'total_archived' => (int) $figures['totalArchived'],
            'placements' => $figures['placements']->values(),
            'conditions' => $figures['conditions']->values(),
            'by_company' => $figures['byCompany']->map(fn ($count, $code) => ['company' => (string) $code, 'count' => $count])->values(),
            'by_type' => $figures['byType']->map(fn ($count, $type) => ['type' => (string) $type, 'count' => $count])->values(),
            'issues' => $figures['issues']->map(fn (array $issue) => ['code' => $issue['code'], 'label' => $issue['label'], 'count' => $issue['count']])->values(),
            'next_free_dmr_ids' => array_values($figures['nextIds']),
        ]]);
    }
}
