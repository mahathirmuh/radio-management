<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\RadioManagement\Models\RadioHistory;

/**
 * One line of "Riwayat Radio": what happened to a radio, when, and who wrote it.
 *
 * @mixin RadioHistory
 */
class RadioHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RadioHistory $line */
        $line = $this->resource;

        return [
            'id' => $line->id,
            'radio' => $line->radio === null ? null : [
                'id' => $line->radio->id,
                'display_name' => $line->radio->display_name,
                'user_name' => $line->radio->user_name,
                'deleted' => $line->radio->trashed(),
            ],
            'event_date' => $line->event_date?->toDateString(),
            /** "Serah Terima", "Program", "Arsip", ... (see /references). */
            'action' => $line->action,
            'description' => $line->description,
            'document_no' => $line->document_no,
            /** For a change of data: field => {label, old, new}. */
            'changes' => $line->changes,
            /** Who wrote it; null for the system (the Excel import). */
            'user' => $line->user === null ? null : ['id' => $line->user->id, 'name' => $line->user->name],
            'created_at' => $line->created_at?->toIso8601String(),
        ];
    }
}
