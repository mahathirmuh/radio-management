<?php

namespace Modules\ApprovalManagement\Models;

use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalStepInstance extends Model
{
    protected $table = 'approval_step_instances';

    protected $fillable = [
        'approval_id',
        'stage_instance_id',
        'step_id',
        'name',
        'order',
        'user_id',
        'role_id',
        'status',
        'fields_schema',
        'acted_by',
        'acted_at',
        'note',
        'is_adhoc',
        'inserted_by',
    ];

    protected $casts = [
        'order' => 'decimal:2',
        'fields_schema' => 'array',
        'acted_at' => 'datetime',
        'is_adhoc' => 'boolean',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function stageInstance()
    {
        return $this->belongsTo(ApprovalStageInstance::class, 'stage_instance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function actedBy()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function fieldValues()
    {
        return $this->hasMany(ApprovalFieldValue::class, 'step_instance_id');
    }

    public function attachments()
    {
        return $this->hasMany(ApprovalAttachment::class, 'step_instance_id');
    }
}
