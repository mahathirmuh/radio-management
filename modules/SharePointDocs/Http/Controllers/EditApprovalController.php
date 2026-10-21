<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SharePointDocs\Models\EditRequest;
use Modules\SharePointDocs\Services\CellEdits;
use Modules\SharePointDocs\Services\EditException;
use Modules\SharePointDocs\Services\EditRequestQuery;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * The page "Persetujuan Edit": the requests to change a cell of a SharePoint workbook, and their approval.
 *
 * Who may do what is the menu's: opening the page and the list need Read, approving and rejecting need Update
 * (the routes are PATCH). That nobody approves their own request is CellEdits', and so is the audit trail of it.
 */
class EditApprovalController extends Controller
{
    public function __construct(private readonly CellEdits $edits)
    {
    }

    public function index()
    {
        return view('SharePointDocs::edit-approvals', [
            'pageTitle' => __('SharePointDocs::approvals.title'),
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route('documents.edit-approvals.index', [], false), 'label' => __('SharePointDocs::approvals.title'), 'active' => true],
            ],
            'statuses' => EditRequest::names(),
        ]);
    }

    /** One page of the requests, newest first; "status" and "search" narrow them. */
    public function list(Request $request): JsonResponse
    {
        if (! CellEdits::ready()) {
            return response()->json(['message' => __('SharePointDocs::edits.not_ready')], 503);
        }

        $terms = EditRequestQuery::terms((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('paginated', 25), 1), 100);
        $page = EditRequestQuery::page($terms, (string) $request->query('status', ''), $perPage, (int) $request->query('page', 1));
        $user = $request->user();
        $mayDecide = MenuAccess::allows($user, 'documents.edit-approvals.approve', 'Update');

        return response()->json([
            'data' => $page->getCollection()->map(fn (EditRequest $one) => $this->present($one, $mayDecide, (int) $user->id))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'from' => $page->firstItem(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
            ],
            // Of everything that matches the words, whatever status is shown.
            'counts' => EditRequestQuery::counts($terms),
            'may_decide' => $mayDecide,
        ]);
    }

    public function approve(Request $request, EditRequest $editRequest): JsonResponse
    {
        try {
            $done = $this->edits->approve($editRequest, $request->user());
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        // Why it was not written (or that it was there already), in the approver's language.
        $failure = $done->failureIn();

        return match ($done->status) {
            EditRequest::APPLIED => response()->json([
                'message' => $failure ?: __('SharePointDocs::approvals.applied', ['cell' => $done->cell]),
                'status' => $done->status,
            ]),
            EditRequest::CONFLICT => response()->json(['message' => $failure, 'status' => $done->status], 409),
            default => response()->json(['message' => $failure, 'status' => $done->status], 502),
        };
    }

    public function reject(Request $request, EditRequest $editRequest): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        try {
            $done = $this->edits->reject($editRequest, $request->user(), (string) ($data['note'] ?? ''));
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['message' => __('SharePointDocs::approvals.rejected', ['cell' => $done->cell]), 'status' => $done->status]);
    }

    /** @return array<string, mixed> */
    private function present(EditRequest $one, bool $mayDecide, int $me): array
    {
        $mine = (int) $one->requested_by === $me;
        $locale = app()->getLocale();
        $open = in_array($one->status, [EditRequest::PENDING, EditRequest::FAILED], true);
        $page = config("sharepoint-docs.workbooks.{$one->workbook}") === null ? null : route("documents.{$one->workbook}.index", [], false)
            . '?' . http_build_query(['sheet' => $one->sheet, 'line' => $one->line]);

        return [
            'id' => $one->id,
            'workbook' => (string) config("sharepoint-docs.workbooks.{$one->workbook}.title", $one->workbook),
            'sheet' => $one->sheet,
            'cell' => $one->cell,
            'line' => $one->line,
            'column' => (string) $one->column_title,
            'row_key' => (string) $one->anchor_value,
            'old' => $one->oldShown($locale),
            'new' => $one->newShown($locale),
            'status' => $one->status,
            'status_label' => EditRequest::statusName($one->status, $locale),
            'requested_by' => $one->requested_by_name,
            'requested_at' => $one->created_at?->format('d-m-Y H:i'),
            'decided_by' => $one->decided_by_name,
            'decided_at' => $one->decided_at?->format('d-m-Y H:i'),
            'note' => $one->decision_note,
            'failure' => $one->failure,
            'applied_at' => $one->applied_at?->format('d-m-Y H:i'),
            // Written straight away by its maker (K13h): not asked for, so not approved by somebody else.
            'direct' => (bool) $one->direct,
            'mine' => $mine,
            // The buttons: approving is for who may (and not for the one who asked); a conflict cannot be approved, only turned down.
            'can_approve' => $mayDecide && ! $mine && $open,
            'can_reject' => $mayDecide && in_array($one->status, [EditRequest::PENDING, EditRequest::FAILED, EditRequest::CONFLICT], true),
            'open' => $page,
        ];
    }
}
