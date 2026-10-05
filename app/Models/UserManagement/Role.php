<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\UserManagement\Menu;

/**
 * Class Role
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @property Collection|Permission[] $permissions
 * @property Collection|User[] $users
 *
 * @package App\Models
 */
class Role extends Model
{
    use SoftDeletes;
    protected $table = 'roles';



    protected $fillable = [
        'company_id',
        'name',
        'func_role',
        'setting_role',
        'home_page'
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot('deleted_at')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('id', 'deleted_at')
            ->withTimestamps();
    }

    public function menu()
    {
        return $this->belongsToMany(Menu::class, 'menu_role', 'role_id', 'menu_id')
            ->withTimestamps();
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class)
            ->withTimestamps();
    }
    //Get Menu yang dimiliki Role
    public function getMenus(...$menuId)
    {
        return $this->menus()->whereIn('menu.id', $menuId)->exists();
    }

    //Get Permission yang dimiliki Role untuk Menu tertentu
    public function hasMenuPermission(int $menuId, int $permissionId)
    {
        return MenuRolePermission::where('role_id', $this->id)
            ->where('menu_id', $menuId)
            ->where('permission_id', $permissionId)
            ->exists();
    }
    /*
	Catatan Nanda : Hati-hati, kamu tidak tahu apa yang kamu hapus :)).
	[MCG 2023]
	*/
}
