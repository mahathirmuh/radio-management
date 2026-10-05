<?php

namespace Modules\ApprovalManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalAttachment extends Model
{
    protected $table = 'approval_attachments';

    protected $fillable = [
        'approval_id',
        'step_instance_id',
        'field_key',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function stepInstance()
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'step_instance_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
