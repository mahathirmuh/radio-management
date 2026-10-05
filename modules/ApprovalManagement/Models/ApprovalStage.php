<?php

namespace Modules\ApprovalManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalStage extends Model
{
    use SoftDeletes;

    protected $table = 'approval_stages';

    protected $fillable = [
        'approval_flow_id',
        'name',
        'stage_order',
        'rule',
        'quorum_count',
    ];

    protected $casts = [
        'stage_order' => 'integer',
        'quorum_count' => 'integer',
    ];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class, 'stage_id')->orderBy('step_order');
    }
}
