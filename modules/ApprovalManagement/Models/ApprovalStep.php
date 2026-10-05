<?php

namespace Modules\ApprovalManagement\Models;

use App\Models\UserManagement\Role;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $table = 'approval_steps';

    protected $fillable = ['name', 'approval_flow_id', 'stage_id', 'step_order', 'user_id', 'role_id', 'is_final_step', 'is_special_case'];

    public function flow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function stage()
    {
        return $this->belongsTo(ApprovalStage::class, 'stage_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function fields()
    {
        return $this->hasMany(ApprovalStepField::class, 'approval_step_id')->orderBy('sort_order');
    }
}
