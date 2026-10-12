<?php

namespace Modules\RadioManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Services\RadioIdService;

/**
 * One physical radio. `dmr_id` is the "Radio ID" programmed into the radio; its
 * hexadecimal form is the MDC code.
 *
 * @property int $id
 * @property int|null $dmr_id
 * @property string|null $serial_number
 * @property Placement $placement
 * @property Condition $condition
 */
class Radio extends Model
{
    public const OWNER_MTI = 'MTI';

    public const OWNER_OTHER = 'Non MTI';

    use SoftDeletes;

    protected $table = 'radios';

    protected $fillable = [
        'dmr_id',
        'serial_number',
        'radio_type_id',
        'owner_company_id',
        'user_name',
        'department_id',
        'company_id',
        'channel_template_id',
        'last_programmed_at',
        'placement',
        'condition',
        'last_verified_at',
        'document_no',
        'notes',
        'needs_review',
        'review_notes',
        'archived_at',
        'archive_reason',
        'previous_dmr_id',
        'source_row',
        'source_sheet',
        'source_data',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dmr_id' => 'integer',
        'previous_dmr_id' => 'integer',
        'source_row' => 'integer',
        'last_programmed_at' => 'date',
        'last_verified_at' => 'date',
        'archived_at' => 'date',
        'needs_review' => 'boolean',
        'source_data' => 'array',
        'placement' => Placement::class,
        'condition' => Condition::class,
    ];

    protected $attributes = [
        'placement' => 'di_user',
        'condition' => 'belum_dicek',
        'needs_review' => false,
    ];

    public function radioType()
    {
        return $this->belongsTo(RadioType::class);
    }

    public function ownerCompany()
    {
        return $this->belongsTo(RadioCompany::class, 'owner_company_id');
    }

    /**
     * "Owner" as the register shows it: MTI or Non MTI, by the company that owns the radio ("Pemilik Aset");
     * null while no owner is filled in. Nothing is stored for it, so it cannot contradict the owner.
     */
    public function ownerGroup(): ?string
    {
        $code = $this->ownerCompany?->code;
        if ($code === null) {
            return null;
        }

        return strcasecmp(trim((string) $code), (string) config('radio.owner_mti_code', 'MTI')) === 0 ? self::OWNER_MTI : self::OWNER_OTHER;
    }

    public function company()
    {
        return $this->belongsTo(RadioCompany::class, 'company_id');
    }

    public function department()
    {
        return $this->belongsTo(RadioDepartment::class);
    }

    public function channelTemplate()
    {
        return $this->belongsTo(ChannelTemplate::class);
    }

    public function histories()
    {
        return $this->hasMany(RadioHistory::class)->orderByDesc('event_date')->orderByDesc('id');
    }

    // Users are soft-deleted: the register keeps saying who did it after an account is removed.
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function getMdcCodeAttribute(): ?string
    {
        return RadioIdService::mdc($this->dmr_id);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Changes on every save. The edit form sends back the version it was loaded with, so a
     * save based on an older state can be refused (see RadioService::update).
     */
    public function version(): ?string
    {
        return $this->updated_at?->format('YmdHisv');
    }

    /** Short label used in history lists, e.g. "ID 1010 · 867TBF3759". */
    public function getDisplayNameAttribute(): string
    {
        $parts = [];
        if ($this->dmr_id) {
            $parts[] = 'ID ' . $this->dmr_id;
        } elseif ($this->previous_dmr_id) {
            $parts[] = 'ex-ID ' . $this->previous_dmr_id;
        }
        $parts[] = $this->serial_number ?: 'tanpa SN';

        return implode(' · ', $parts);
    }
}
