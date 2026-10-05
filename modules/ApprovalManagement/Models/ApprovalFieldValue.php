<?php

namespace Modules\ApprovalManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalFieldValue extends Model
{
    protected $table = 'approval_field_values';

    protected $fillable = [
        'approval_id',
        'step_instance_id',
        'field_key',
        'label',
        'type',
        'value',
    ];

    public function approval()
    {
        return $this->belongsTo(Approval::class);
    }

    public function stepInstance()
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'step_instance_id');
    }
}
