<?php

namespace Modules\RadioManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * What is known of an item of "Perlu Perhatian" besides its check: when it was first seen, to whom it is given, and why
 * it was set aside (AttentionInbox).
 *
 * @property int $id
 * @property string $check_code a key of RadioCheckService::ISSUES
 * @property string $subject the radio's id, or the SN / Radio ID that several radios share
 * @property Carbon $first_seen_at
 * @property ?int $assigned_to
 * @property ?int $assigned_by
 * @property ?Carbon $assigned_at
 * @property ?string $dismissed_reason
 * @property ?int $dismissed_by
 * @property ?Carbon $dismissed_at
 * @property ?string $dismissed_fingerprint
 */
class RadioCheckState extends Model
{
    protected $table = 'radio_check_states';

    protected $guarded = [];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'assigned_to' => 'integer',
        'assigned_by' => 'integer',
        'assigned_at' => 'datetime',
        'dismissed_by' => 'integer',
        'dismissed_at' => 'datetime',
    ];

    /**
     * Whether the table is there. The code is live as soon as it is saved, the migration is run by hand: until then the
     * checks are still shown, without first seen, assignments and dismissals. Asked once a minute.
     */
    public static function ready(): bool
    {
        return Cache::remember('radio-check-states-table', 60, fn () => Schema::hasTable('radio_check_states'));
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function dismisser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by')->withTrashed();
    }
}
