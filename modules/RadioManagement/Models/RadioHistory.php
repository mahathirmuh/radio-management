<?php

namespace Modules\RadioManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class RadioHistory extends Model
{
    protected $table = 'radio_histories';

    protected $fillable = [
        'radio_id',
        'event_date',
        'action',
        'description',
        'document_no',
        'changes',
        'user_id',
    ];

    protected $casts = [
        'event_date' => 'date',
        'changes' => 'array',
    ];

    public function radio()
    {
        return $this->belongsTo(Radio::class)->withTrashed();
    }

    /** Users are soft-deleted: the log keeps the name of who did it after an account is removed. */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
