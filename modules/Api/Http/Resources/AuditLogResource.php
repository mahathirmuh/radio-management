<?php

namespace Modules\Api\Http\Resources;

use App\Models\Log\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the audit trail.
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AuditLog $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'at' => $entry->created_at?->toIso8601String(),
            /** Who did it: the account (null for a failed sign-in, or a command of the server). */
            'user' => [
                'id' => $entry->user_id === null ? null : (int) $entry->user_id,
                'name' => $entry->actorName(),
                'email' => $entry->user_email,
            ],
            /** The key, e.g. "radio.updated". */
            'action' => $entry->action,
            /** In words: "updated radio". */
            'activity' => $entry->activityLabel(),
            /** What it is about, when it is about a record: its kind, its id, and what it was called then. */
            'entity' => $entry->entity_type === null ? null : [
                'type' => $entry->entity_type,
                'id' => $entry->entity_id === null ? null : (int) $entry->entity_id,
                'label' => $entry->entity_label,
            ],
            /** The fields that changed, by the names of the page: before ... */
            'old_values' => is_array($entry->old_values) ? $entry->old_values : null,
            /** ... and after. */
            'new_values' => is_array($entry->new_values) ? $entry->new_values : null,
            'ip_address' => $entry->ip_address,
            /** "API · <token> (#id)" for a change made through the API, null for the pages. */
            'via' => $entry->getAttribute('via'),
        ];
    }
}
