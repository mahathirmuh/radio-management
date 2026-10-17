<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\RadioCheckService;

/**
 * One radio of the register (one physical radio, by its SN). Field names are those of the form, so what is read can
 * be sent back as it is; the master data a radio points at comes with its id and its name.
 *
 * @mixin Radio
 */
class RadioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Radio $radio */
        $radio = $this->resource;

        return [
            'id' => $radio->id,
            /** Radio ID (DMR). Null while the radio is archived: its ID was released. */
            'dmr_id' => $radio->dmr_id,
            /** The Radio ID it had before it was archived, kept for when it is restored. */
            'previous_dmr_id' => $radio->previous_dmr_id,
            /** The Radio ID in hexadecimal (1001 → "3E9"). */
            'mdc_code' => $radio->mdc_code,
            'serial_number' => $radio->serial_number,
            'radio_type' => $radio->radioType === null ? null : [
                'id' => $radio->radioType->id,
                'name' => $radio->radioType->name,
                'category' => $radio->radioType->category,
                'power' => $radio->radioType->power,
            ],
            'owner_company' => self::company($radio->ownerCompany),
            /** "MTI" or "Non MTI", by the company that owns the radio; null while that is not filled in. */
            'owner_group' => $radio->ownerGroup(),
            'user_name' => $radio->user_name,
            'department' => $radio->department === null ? null : ['id' => $radio->department->id, 'name' => $radio->department->name],
            'company' => self::company($radio->company),
            'channel_template' => $radio->channelTemplate === null ? null : ['id' => $radio->channelTemplate->id, 'name' => $radio->channelTemplate->name],
            'placement' => ['value' => $radio->placement->value, 'label' => $radio->placement->label()],
            'condition' => ['value' => $radio->condition->value, 'label' => $radio->condition->label()],
            'last_programmed_at' => $radio->last_programmed_at?->toDateString(),
            'last_verified_at' => $radio->last_verified_at?->toDateString(),
            'document_no' => $radio->document_no,
            'notes' => $radio->notes,
            'needs_review' => (bool) $radio->needs_review,
            'review_notes' => $radio->review_notes,
            'archived' => $radio->isArchived(),
            'archived_at' => $radio->archived_at?->toDateString(),
            'archive_reason' => $radio->archive_reason,
            /** What the column "Cek" of the register flags for this radio. */
            'issues' => array_map(
                fn (array $issue) => ['code' => $issue['code'], 'label' => $issue['label'], 'hint' => $issue['hint']],
                app(RadioCheckService::class)->issuesFor($radio)
            ),
            /** Send it back with a change: a change based on an older state is refused with 409. */
            'version' => $radio->version(),
            'created_at' => $radio->created_at?->toIso8601String(),
            'updated_at' => $radio->updated_at?->toIso8601String(),
        ];
    }

    private static function company($company): ?array
    {
        return $company === null ? null : ['id' => $company->id, 'code' => $company->code, 'name' => $company->name];
    }
}
