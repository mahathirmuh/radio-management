<?php

namespace Modules\ApprovalManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalHistory extends Model
{
    protected $table = 'approval_histories';

    protected $fillable = [
        'approval_id',
        'step_id',
        'user_id',
        'action',
        'note',
        'delegated_to',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function step()
    {
        return $this->belongsTo(ApprovalStep::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function delegatedTo()
    {
        return $this->belongsTo(User::class, 'delegated_to');
    }
}
