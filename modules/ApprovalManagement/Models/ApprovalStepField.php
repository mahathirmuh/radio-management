<?php

namespace Modules\ApprovalManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalStepField extends Model
{
    use SoftDeletes;

    protected $table = 'approval_step_fields';

    protected $fillable = [
        'approval_step_id',
        'key',
        'label',
        'type',
        'is_required',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'sort_order' => 'integer',
    ];

    public function step()
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }
}
