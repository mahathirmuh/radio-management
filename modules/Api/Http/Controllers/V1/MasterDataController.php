<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Services\MasterData;

/**
 * The four master tables of "Master Data Radio" (radio types, companies, departments, channel templates), with the
 * rules of their pages (MasterData). Each route passes its table as the route default `master`.
 *
 * @tags Master data
 */
class MasterDataController extends ApiController
{
    public function __construct(private MasterData $masters)
    {
    }

    /**
     * List the rows of a master table
     *
     * Each row carries `usage`: how many radios of the register (active or archived) point at it.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $master = $this->master($request);
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in($master['sortable'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $this->masters->paginate($master, trim((string) $request->input('search')), $request->input('sort'), $request->input('direction'), $this->perPage($request));
        $usage = $this->masters->usage($master, $page->getCollection()->modelKeys());

        return JsonResource::collection($page->through(fn ($item) => $this->masters->row($master, $item, $usage[$item->id] ?? 0)));
    }

    /**
     * Show a row of a master table
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);

        return response()->json(['data' => $this->masters->row($master, $item, $this->masters->usage($master, [$item->id])[$item->id])]);
    }

    /**
     * Add a row to a master table
     *
     * The fields of the table's form (see the page). A code or name that must be unique is refused with 422 when taken.
     */
    public function store(Request $request): JsonResponse
    {
        $master = $this->master($request);
        $validator = $this->masters->validator($master, $request->all());
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $item = $this->masters->create($master, $validator->validated());

        return response()->json(['data' => $this->masters->row($master, $item, 0)], 201);
    }

    /**
     * Change a row of a master table
     *
     * Switching a row off (`is_active: false`) keeps it on the radios that have it, but it can no longer be picked.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);
        $validator = $this->masters->validator($master, $request->all(), $item->id);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $this->masters->update($master, $item, $validator->validated());

        return response()->json(['data' => $this->masters->row($master, $item, $this->masters->usage($master, [$item->id])[$item->id])]);
    }

    /**
     * Delete a row of a master table
     *
     * Only a row that no radio uses (422 otherwise: switch it off instead).
     */
    public function destroy(Request $request, int $id)
    {
        $master = $this->master($request);
        $item = $master['model']::findOrFail($id);

        try {
            $inUse = $this->masters->delete($master, $item);
        } catch (LockTimeoutException) {
            return response()->json(['message' => self::BUSY], 409);
        }
        if ($inUse > 0) {
            return response()->json(['message' => MasterData::inUseMessage($inUse), 'errors' => ['id' => [MasterData::inUseMessage($inUse)]]], 422);
        }

        return response()->noContent();
    }

    private function master(Request $request): array
    {
        $master = $this->masters->find($request->route('master'));
        abort_if($master === null, 404);

        return $master;
    }
}
