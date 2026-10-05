<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Services\Like;

class RadioHistoryController extends Controller
{
    /** Actions written by the system in addition to the manual ones in config. */
    private const SYSTEM_ACTIONS = ['Dibuat', 'Ubah Data', 'Ubah Status', 'Arsip', 'Dipulihkan', 'Impor', 'Dihapus'];

    public function index()
    {
        return view('RadioManagement::history.index', [
            'pageTitle' => 'Riwayat Radio',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/radio-management/history', 'label' => 'Riwayat Radio', 'active' => true],
            ],
            'actions' => collect(config('radio.history_actions'))->merge(self::SYSTEM_ACTIONS)->unique()->sort()->values(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('paginated', 25), 1), 100);
        $search = trim((string) $request->input('search'));
        $actions = array_filter((array) $request->input('action', []));

        $data = RadioHistory::query()
            ->with(['radio', 'user'])
            ->when($search, function ($query) use ($search) {
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
            ->when($actions, fn ($query) => $query->whereIn('action', $actions))
            ->when($request->date('date_from'), fn ($query, $from) => $query->where('event_date', '>=', $from->toDateString()))
            ->when($request->date('date_to'), fn ($query, $to) => $query->where('event_date', '<=', $to->toDateString()))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate($limit);

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
