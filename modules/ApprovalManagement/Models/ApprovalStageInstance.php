<?php

namespace Modules\ApprovalManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStageInstance extends Model
{
    protected $table = 'approval_stage_instances';

    protected $fillable = [
        'approval_id',
        'stage_id',
        'name',
        'stage_order',
        'rule',
        'quorum_count',
        'status',
    ];

    protected $casts = [
        'stage_order' => 'decimal:2',
        'quorum_count' => 'integer',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function stage()
    {
        return $this->belongsTo(ApprovalStage::class, 'stage_id');
    }

    public function stepInstances()
    {
        return $this->hasMany(ApprovalStepInstance::class, 'stage_instance_id')->orderBy('order');
    }
}
