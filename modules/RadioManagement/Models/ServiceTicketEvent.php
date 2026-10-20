<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A line of the activity of a service ticket: a step it moved on (with what happened, in words), or a note.
 *
 * @property int $id
 * @property int $service_ticket_id
 * @property string $kind status or note
 * @property ?string $status the step it moved to
 * @property ?string $note
 * @property ?int $user_id
 * @property ?string $user_name
 * @property Carbon $created_at
 */
class ServiceTicketEvent extends Model
{
    public const UPDATED_AT = null;

    public const STATUS = 'status';

    public const NOTE = 'note';

    protected $table = 'service_ticket_events';

    protected $guarded = [];

    protected $casts = [
        'service_ticket_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }
}
