<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\SharePointDocs\Models\EditRequest;

/**
 * A request to change one cell of a workbook, and what became of it.
 */
class EditRequestResource extends JsonResource
{
    /**
     * @param bool $mayDecide whether the account of this request's token may approve and turn down requests (Update on
     *     "Persetujuan Edit", with a personal token that writes)
     */
    public function __construct(EditRequest $resource, private readonly bool $mayDecide = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var EditRequest $one */
        $one = $this->resource;
        $mine = (int) $one->requested_by === (int) $request->user()?->id;

        return [
            'id' => $one->id,
            'workbook' => [
                'key' => $one->workbook,
                'title' => (string) config("sharepoint-docs.workbooks.{$one->workbook}.title", $one->workbook),
            ],
            'sheet' => $one->sheet,
            /** The cell, as Excel names it ("C12"). */
            'cell' => $one->cell,
            /** The row in Excel. */
            'line' => (int) $one->line,
            /** The title of the column. */
            'column' => (string) $one->column_title,
            /** What the column that tells the row held when the change was asked for: the change is written only while it still does. */
            'row_key' => $one->anchor_value,
            /** What the cell held, as the table showed it. */
            'old' => $one->oldShown(),
            /** What the cell is to hold, as it is shown. */
            'new' => $one->newShown(),
            /** "text", "number" (a number or a date) or "empty". */
            'new_kind' => $one->new_kind,
            /** pending, processing, applied, rejected, conflict or failed. */
            'status' => $one->status,
            'status_label' => EditRequest::LABELS[$one->status] ?? $one->status,
            'requested_by' => ['id' => $one->requested_by === null ? null : (int) $one->requested_by, 'name' => $one->requested_by_name],
            'requested_at' => $one->created_at?->toIso8601String(),
            'decided_by' => $one->decided_by === null ? null : ['id' => (int) $one->decided_by, 'name' => $one->decided_by_name],
            'decided_at' => $one->decided_at?->toIso8601String(),
            /** Why it was turned down. */
            'note' => $one->decision_note,
            /** Why it was not written (conflict, failed). */
            'failure' => $one->failure,
            'applied_at' => $one->applied_at?->toIso8601String(),
            /** Asked for by the account of this token. */
            'mine' => $mine,
            /** Whether this token may approve it now: nobody approves their own request, and a conflict can only be turned down. */
            'can_approve' => $this->mayDecide && ! $mine && in_array($one->status, [EditRequest::PENDING, EditRequest::FAILED], true),
            'can_reject' => $this->mayDecide && in_array($one->status, [EditRequest::PENDING, EditRequest::FAILED, EditRequest::CONFLICT], true),
        ];
    }
}
