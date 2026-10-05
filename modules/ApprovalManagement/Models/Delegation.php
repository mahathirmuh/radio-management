<?php

namespace Modules\ApprovalManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{

    protected $table = 'delegations';

    protected $fillable = [
        'approval_id',
        'from_user_id',
        'to_user_id',
        'created_by',
        'note',
        'status',
        'start_date',
        'end_date',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
