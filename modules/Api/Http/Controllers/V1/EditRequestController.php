<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Api\Http\Resources\EditRequestResource;
use Modules\SharePointDocs\Models\EditRequest;
use Modules\SharePointDocs\Services\CellEdits;
use Modules\SharePointDocs\Services\EditException;
use Modules\SharePointDocs\Services\EditRequestQuery;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * The page "Persetujuan Edit": the requests to change a cell of a workbook, and their approval. Approving and turning down
 * are a person's decision: only with a personal token, never with the token of an API client, and nobody approves their
 * own request. Approving writes the change to SharePoint (10 a minute per token).
 *
 * @tags Edit requests
 */
class EditRequestController extends ApiController
{
    public const NOT_READY = 'Tabel pengajuan belum dibuat di database. Jalankan "php artisan migrate" dulu.';

    public function __construct(private readonly CellEdits $edits)
    {
    }

    /**
     * List the requests
     *
     * Newest first. `counts` are of everything that matches `search`, whatever `status` is shown. `may_decide`: whether this
     * token may approve and turn down requests.
     */
    public function index(Request $request): JsonResponse
    {
        if (! CellEdits::ready()) {
            return response()->json(['message' => self::NOT_READY], 503);
        }
        $request->validate([
            /** pending, processing, applied, rejected, conflict or failed. */
            'status' => ['nullable', Rule::in(array_keys(EditRequest::LABELS))],
            /** Words that must all be somewhere in the cell, sheet, column, values, names or row key. */
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $terms = EditRequestQuery::terms((string) $request->input('search', ''));
        $page = EditRequestQuery::page($terms, (string) $request->input('status', ''), $this->perPage($request), $request->integer('page', 1));
        $mayDecide = $this->mayDecide($request);

        return response()->json([
            'data' => $page->getCollection()->map(fn (EditRequest $one) => (new EditRequestResource($one, $mayDecide))->resolve($request))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'from' => $page->firstItem(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
            ],
            /** Requests of each status. */
            'counts' => EditRequestQuery::counts($terms),
            'may_decide' => $mayDecide,
        ]);
    }

    /**
     * Show a request
     */
    public function show(Request $request, int $editRequest): JsonResponse
    {
        $one = $this->find($editRequest);

        return response()->json(['data' => (new EditRequestResource($one, $this->mayDecide($request)))->resolve($request)]);
    }

    /**
     * Approve a request
     *
     * The workbook is read as it is now and the cell is written only if it, and the row it is in, still hold what they held
     * when the change was asked for, and nobody saved the file in between. The answer is the request as it ended:
     * `applied` (200), `conflict` (409: nothing was written, ask again), or `failed` (502: SharePoint could not be written to;
     * it can be approved again). Not your own request (403), not one that was decided already (409). Personal token only.
     */
    public function approve(Request $request, int $editRequest): JsonResponse
    {
        $one = $this->find($editRequest);

        try {
            $done = $this->edits->approve($one, $request->user());
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
        $data = (new EditRequestResource($done, $this->mayDecide($request)))->resolve($request);

        return match ($done->status) {
            EditRequest::APPLIED => response()->json(['message' => $done->failure ?: "Perubahan sel {$done->cell} sudah ditulis ke SharePoint.", 'data' => $data]),
            EditRequest::CONFLICT => response()->json(['message' => $done->failure, 'data' => $data], 409),
            default => response()->json(['message' => $done->failure, 'data' => $data], 502),
        };
    }

    /**
     * Turn a request down
     *
     * A request that waits, failed or met a conflict; nothing is written. Personal token only.
     */
    public function reject(Request $request, int $editRequest): JsonResponse
    {
        $one = $this->find($editRequest);
        $data = $request->validate([
            /** Why (shown to whoever asked). */
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $done = $this->edits->reject($one, $request->user(), (string) ($data['note'] ?? ''));
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'message' => "Pengajuan perubahan sel {$done->cell} ditolak.",
            'data' => (new EditRequestResource($done, $this->mayDecide($request)))->resolve($request),
        ]);
    }

    private function find(int $id): EditRequest
    {
        abort_unless(CellEdits::ready(), 503, self::NOT_READY);
        $one = EditRequest::query()->find($id);
        abort_if($one === null, 404);

        return $one;
    }

    /** Update on "Persetujuan Edit", with a personal token that writes. */
    private function mayDecide(Request $request): bool
    {
        $user = $request->user();

        return ! $user->isApiClient()
            && $this->tokenWrites($request)
            && MenuAccess::allows($user, 'documents.edit-approvals.approve', 'Update');
    }
}
