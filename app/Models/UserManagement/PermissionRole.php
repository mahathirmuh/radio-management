<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PermissionRole
 * 
 * @property int $role_id
 * @property int $permission_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Permission $permission
 * @property Role $role
 *
 * @package App\Models
 */
class PermissionRole extends Model
{
	protected $table = 'permission_role';
	public $incrementing = false;

	protected $casts = [
		'role_id' => 'int',
		'permission_id' => 'int'
	];

	protected $fillable = [
		'role_id',
		'permission_id'
	];

	public function permission()
	{
		return $this->belongsTo(Permission::class);
	}

	public function role()
	{
		return $this->belongsTo(Role::class);
	}
}
