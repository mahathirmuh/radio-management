<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Permission
 * 
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Role[] $roles
 *
 * @package App\Models
 */
class Permission extends Model
{
	use SoftDeletes;
	protected $table = 'permissions';

	protected $fillable = [
		'name'
	];

	public function roles()
	{
		return $this->belongsToMany(Role::class)
					->withPivot('deleted_at')
					->withTimestamps();
	}
}
