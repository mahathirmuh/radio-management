<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A radio found in a stock opname: who has it (the user of the register, somebody else, nobody), in what condition, with
 * a note; and the service ticket opened for it there, if any.
 *
 * @property int $id
 * @property int $session_id
 * @property int $radio_id
 * @property string $radio_label
 * @property string $holder register, other or none
 * @property ?string $holder_name
 * @property string $condition a value of Condition
 * @property ?string $note
 * @property ?string $ticket_number
 * @property ?int $scanned_by
 * @property ?Carbon $updated_at
 */
class RadioOpnameItem extends Model
{
    public const HOLDERS = ['register', 'other', 'none'];

    protected $table = 'radio_opname_items';

    protected $guarded = [];

    protected $casts = [
        'session_id' => 'integer',
        'radio_id' => 'integer',
        'scanned_by' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RadioOpnameSession::class, 'session_id');
    }

    /** The radio, also when it was archived or deleted since. */
    public function radio(): BelongsTo
    {
        return $this->belongsTo(Radio::class)->withTrashed();
    }
}
