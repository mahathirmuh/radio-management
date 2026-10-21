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
	 * The name of the entry in the language of the page (lang/<code>/menu.php): an entry by its route, a group by its
	 * title in the table. One without a name there (added to the table later) shows its title as it is.
	 */
	public function label(): string
	{
		if ($this->route) {
			return self::routeLabel($this->route, (string) $this->title);
		}
		$groups = __('menu.groups');

		return is_array($groups) && isset($groups[$this->title]) ? (string) $groups[$this->title] : (string) $this->title;
	}

	/** The name of the entry of $route in the language of the page, else $fallback. */
	public static function routeLabel(string $route, string $fallback): string
	{
		$names = __('menu.routes');

		return is_array($names) && isset($names[$route]) ? (string) $names[$route] : $fallback;
	}

	/** The name of a section of the sidebar ("Dokumen", "Settings") in the language of the page. */
	public static function sectionLabel(?string $section): string
	{
		$names = __('menu.sections');

		return is_array($names) && isset($names[$section]) ? (string) $names[$section] : (string) $section;
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
