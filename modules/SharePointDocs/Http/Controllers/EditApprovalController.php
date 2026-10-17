<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SharePointDocs\Models\EditRequest;
use Modules\SharePointDocs\Services\CellEdits;
use Modules\SharePointDocs\Services\EditException;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * The page "Persetujuan Edit": the requests to change a cell of a SharePoint workbook, and their approval.
 *
 * Who may do what is the menu's: opening the page and the list need Read, approving and rejecting need Update
 * (the routes are PATCH). That nobody approves their own request is CellEdits'.
 */
class EditApprovalController extends Controller
{
    public function __construct(private readonly CellEdits $edits)
    {
    }

    public function index()
    {
        return view('SharePointDocs::edit-approvals', [
            'pageTitle' => 'Persetujuan Edit',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route('documents.edit-approvals.index', [], false), 'label' => 'Persetujuan Edit', 'active' => true],
            ],
            'statuses' => EditRequest::LABELS,
        ]);
    }

    /** One page of the requests, newest first; "status" and "search" narrow them. */
    public function list(Request $request): JsonResponse
    {
        if (! CellEdits::ready()) {
            return response()->json(['message' => 'Tabel pengajuan belum dibuat di database. Jalankan "php artisan migrate" dulu.'], 503);
        }

        $status = (string) $request->query('status', '');
        $terms = preg_split('/\s+/u', mb_substr(trim((string) $request->query('search', '')), 0, 100), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Every request that matches the words typed (a word may be in any of the columns that are shown).
        $matching = function () use ($terms) {
            $query = EditRequest::query();
            foreach ($terms as $term) {
                // (SQL Server's LIKE has no backslash: its own wildcards are made plain by putting them in brackets.)
                $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
                $query->where(fn ($q) => $q
                    ->where('cell', 'like', $like)->orWhere('sheet', 'like', $like)->orWhere('column_title', 'like', $like)
                    ->orWhere('old_text', 'like', $like)->orWhere('new_value', 'like', $like)
                    ->orWhere('requested_by_name', 'like', $like)->orWhere('decided_by_name', 'like', $like)
                    ->orWhere('anchor_value', 'like', $like));
            }

            return $query;
        };

        // The counts are of everything that matches the words, whatever status is shown.
        $counts = array_fill_keys(array_keys(EditRequest::LABELS), 0);
        foreach ($matching()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $name => $total) {
            $counts[$name] = (int) $total;
        }
        $query = $matching()->when(array_key_exists($status, EditRequest::LABELS), fn ($q) => $q->where('status', $status));

        $perPage = min(max((int) $request->query('paginated', 25), 1), 100);
        $page = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', max(1, (int) $request->query('page', 1)));
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
            'counts' => $counts,
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
        // Approved: what became of it is said too (written to SharePoint, or not, and why).
        app(AuditLogger::class)->log(AuditLogger::EDIT_APPROVED, $done, new: array_filter([
            'Value' => $done->newShown(),
            'Result' => EditRequest::LABELS[$done->status] ?? $done->status,
            'Note' => $done->failure ?: null,
        ], fn ($value) => $value !== null), label: self::auditLabel($done));

        return match ($done->status) {
            EditRequest::APPLIED => response()->json([
                'message' => $done->failure ?: "Perubahan sel {$done->cell} sudah ditulis ke SharePoint.",
                'status' => $done->status,
            ]),
            EditRequest::CONFLICT => response()->json(['message' => $done->failure, 'status' => $done->status], 409),
            default => response()->json(['message' => $done->failure, 'status' => $done->status], 502),
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
        app(AuditLogger::class)->log(AuditLogger::EDIT_REJECTED, $done, new: array_filter([
            'Value' => $done->newShown(),
            'Note' => filled($done->decision_note) ? $done->decision_note : null,
        ], fn ($value) => $value !== null), label: self::auditLabel($done));

        return response()->json(['message' => "Pengajuan perubahan sel {$done->cell} ditolak.", 'status' => $done->status]);
    }

    /** The cell a request is about, as the audit trail names it: "ICT Masterlist · COF!B12". */
    public static function auditLabel(EditRequest $one): string
    {
        return config("sharepoint-docs.workbooks.{$one->workbook}.title", $one->workbook) . ' · ' . $one->sheet . '!' . $one->cell;
    }

    /** @return array<string, mixed> */
    private function present(EditRequest $one, bool $mayDecide, int $me): array
    {
        $mine = (int) $one->requested_by === $me;
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
            'old' => $one->oldShown(),
            'new' => $one->newShown(),
            'status' => $one->status,
            'status_label' => EditRequest::LABELS[$one->status] ?? $one->status,
            'requested_by' => $one->requested_by_name,
            'requested_at' => $one->created_at?->format('d-m-Y H:i'),
            'decided_by' => $one->decided_by_name,
            'decided_at' => $one->decided_at?->format('d-m-Y H:i'),
            'note' => $one->decision_note,
            'failure' => $one->failure,
            'applied_at' => $one->applied_at?->format('d-m-Y H:i'),
            'mine' => $mine,
            // The buttons: approving is for who may (and not for the one who asked); a conflict cannot be approved, only turned down.
            'can_approve' => $mayDecide && ! $mine && $open,
            'can_reject' => $mayDecide && in_array($one->status, [EditRequest::PENDING, EditRequest::FAILED, EditRequest::CONFLICT], true),
            'open' => $page,
        ];
    }
}
