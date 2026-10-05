<?php

namespace Modules\ApprovalManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $table = 'approvals';

    protected $fillable = [
        'approval_flow_id',
        'model_type',
        'model_id',
        'current_step_id',
        'current_stage_instance_id',
        'status',
        'takeover_by',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function currentStep()
    {
        return $this->belongsTo(ApprovalStep::class, 'current_step_id');
    }

    public function currentStageInstance()
    {
        return $this->belongsTo(ApprovalStageInstance::class, 'current_stage_instance_id');
    }

    public function stageInstances()
    {
        return $this->hasMany(ApprovalStageInstance::class)->orderBy('stage_order');
    }

    public function stepInstances()
    {
        return $this->hasMany(ApprovalStepInstance::class)->orderBy('order');
    }

    public function fieldValues()
    {
        return $this->hasMany(ApprovalFieldValue::class);
    }

    public function attachments()
    {
        return $this->hasMany(ApprovalAttachment::class);
    }

    public function takeoverUser()
    {
        return $this->belongsTo(User::class, 'takeover_by');
    }

    public function histories()
    {
        return $this->hasMany(ApprovalHistory::class);
    }

    public function delegations()
    {
        return $this->hasMany(Delegation::class);
    }

    public function model()
    {
        return $this->morphTo();
    }
}
