<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SubMenu
 *
 * @property int $id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Collection|Role[] $roles
 *
 * @package App\Models
 */
class Menu extends Model
{
	protected $table = 'menu';

	protected $fillable = [
		'title',
		'parent_id',
		'route',
		'icon',
		'order',
		'section',
	];

	public function roles()
	{
		return $this->belongsToMany(Role::class, 'menu_role', 'menu_id')
					->withTimestamps();
	}

	public function parent()
	{
		return $this->belongsTo(Menu::class, 'parent_id');
	}

	public function children()
	{
		return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
	}

	/**
	 * A group is only shown if it's itself granted and at least one of its
	 * children is visible too, so revoking the group hides the whole branch
	 * without having to touch every child individually.
	 */
	public function isVisibleFor($allowedMenuIds): bool
	{
		if (!in_array($this->id, $allowedMenuIds)) {
			return false;
		}

		if ($this->children->isEmpty()) {
			return true;
		}

		return $this->children->contains(fn (Menu $child) => $child->isVisibleFor($allowedMenuIds));
	}
}
