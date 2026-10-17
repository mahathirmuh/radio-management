<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Services\RadioHistoryQuery;

class RadioHistoryController extends Controller
{
    public function index()
    {
        return view('RadioManagement::history.index', [
            'pageTitle' => 'Riwayat Radio',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/radio-management/history', 'label' => 'Riwayat Radio', 'active' => true],
            ],
            'actions' => collect(RadioHistoryQuery::actions()),
        ]);
    }

    public function list(Request $request, RadioHistoryQuery $histories): JsonResponse
    {
        $limit = min(max((int) $request->input('paginated', 25), 1), 100);
        $data = $histories->build(
            trim((string) $request->input('search')),
            array_values(array_filter((array) $request->input('action', []))),
            $request->date('date_from'),
            $request->date('date_to'),
        )->paginate($limit);

        return response()->json([
            'data' => $data->getCollection()->map(fn (RadioHistory $history) => [
                'id' => $history->id,
                'event_date' => $history->event_date?->format('d-m-Y'),
                'action' => $history->action,
                'description' => $history->description,
                'document_no' => $history->document_no,
                'user' => $history->user?->name ?? 'Sistem',
                'radio' => $history->radio?->display_name,
                'radio_user' => $history->radio?->user_name,
                'detail_url' => $history->radio && ! $history->radio->trashed()
                    ? route('radio-management.radio.detail', $history->radio)
                    : null,
            ])->values(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
        ]);
    }
}
