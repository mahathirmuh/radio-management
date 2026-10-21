<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RadioManagement\Services\MasterData;

/**
 * One controller for the four small master tables. Each route group passes its
 * `master` key as a route default (see Routes/web/radio-management.php). What the
 * tables hold and how a row is checked and saved is MasterData's (the API uses it too).
 */
class MasterDataController extends Controller
{
    public const KEYS = MasterData::KEYS;

    public function __construct(private MasterData $masters)
    {
    }

    public function index(Request $request)
    {
        $master = $this->master($request);

        return view('RadioManagement::master.index', [
            'pageTitle' => $master['title'],
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '#', 'label' => 'Master Data Radio', 'active' => false],
                ['url' => $request->url(), 'label' => $master['title'], 'active' => true],
            ],
            'masterKey' => $master['key'],
            'master' => $master,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $master = $this->master($request);
        $limit = min(max((int) $request->input('paginated', 10), 1), 100);

        $data = $this->masters->paginate($master, trim((string) $request->input('search')), $request->input('orderBy'), $request->input('orderDir'), $limit);
        $usage = $this->masters->usage($master, $data->getCollection()->modelKeys());

        return response()->json([
            'data' => $data->getCollection()->map(fn (Model $item) => $this->masters->row($master, $item, $usage[$item->id] ?? 0))->values(),
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

    public function show(Request $request, $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);

        return response()->json(['data' => $this->masters->row($master, $item, $this->masters->usage($master, [$item->id])[$item->id])]);
    }

    public function store(Request $request): JsonResponse
    {
        $master = $this->master($request);
        $validator = $this->masters->validator($master, $request->all());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $this->masters->create($master, $validator->validated());

        return response()->json(['message' => __("RadioManagement::master.tables.{$master['key']}.created")]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);
        $validator = $this->masters->validator($master, $request->all(), $item->id);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $this->masters->update($master, $item, $validator->validated());

        return response()->json(['message' => __("RadioManagement::master.tables.{$master['key']}.saved")]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);

        try {
            $inUse = $this->masters->delete($master, $item);
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => __('RadioManagement::register.messages.busy'),
                'errors' => [__('RadioManagement::master.messages.busy_text')],
            ], 409);
        }

        if ($inUse > 0) {
            return $this->invalid([MasterData::inUseMessage($inUse)]);
        }

        return response()->json(['message' => __("RadioManagement::master.tables.{$master['key']}.deleted")]);
    }

    private function master(Request $request): array
    {
        $master = $this->masters->find($request->route('master'));
        abort_if($master === null, 404);

        return $master;
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => __('RadioManagement::register.messages.invalid'), 'errors' => $errors], 422);
    }
}
