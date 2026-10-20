<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * A stock opname of one department (menu "Stock Opname"): the radios found there, scanned one by one on a phone
 * (RadioOpnameItem), and sent to the register once (RadioOpnames::submit).
 *
 * @property int $id
 * @property int $department_id
 * @property string $department_name
 * @property ?int $company_id
 * @property ?string $company_name
 * @property string $status open or submitted
 * @property int $expected
 * @property ?int $started_by
 * @property ?int $submitted_by
 * @property ?Carbon $submitted_at
 * @property ?array $summary
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class RadioOpnameSession extends Model
{
    public const OPEN = 'open';

    public const SUBMITTED = 'submitted';

    protected $table = 'radio_opname_sessions';

    protected $guarded = [];

    protected $casts = [
        'department_id' => 'integer',
        'company_id' => 'integer',
        'expected' => 'integer',
        'started_by' => 'integer',
        'submitted_by' => 'integer',
        'submitted_at' => 'datetime',
        'summary' => 'array',
    ];

    /**
     * Whether the tables are there. The code is live as soon as it is saved, the migration is run by hand: until then the
     * page says so, and nothing else changes. Asked once a minute.
     */
    public static function ready(): bool
    {
        return Cache::remember('radio-opname-tables', 60, fn () => Schema::hasTable('radio_opname_sessions') && Schema::hasTable('radio_opname_items'));
    }

    /** How many sessions are open (0 while the tables are not there). */
    public static function openCount(): int
    {
        return self::ready() ? self::query()->where('status', self::OPEN)->count() : 0;
    }

    public function items(): HasMany
    {
        return $this->hasMany(RadioOpnameItem::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** "Process Plant", or "Process Plant · MTI" when it was one company's. */
    public function place(): string
    {
        return $this->department_name . ($this->company_name ? ' · ' . $this->company_name : '');
    }
}
