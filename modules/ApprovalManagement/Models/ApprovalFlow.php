<?php

namespace Modules\ApprovalManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    protected $table = 'approval_flows';

    protected $fillable = ['name', 'description', 'status'];

    public function stages()
    {
        return $this->hasMany(ApprovalStage::class)->orderBy('stage_order');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }
}
