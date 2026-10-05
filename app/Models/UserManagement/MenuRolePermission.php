<?php

namespace App\Models\UserManagement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MenuRolePermission
 *
 * @property int $id
 * @property int $menu_id
 * @property int $role_id
 * @property int $permission_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Menu $menu
 * @property Role $role
 * @property Permission $permission
 *
 * @package App\Models
 */
class MenuRolePermission extends Model
{
    protected $table = 'menu_role_permission';

    protected $casts = [
        'menu_id' => 'int',
        'role_id' => 'int',
        'permission_id' => 'int',
    ];

    protected $fillable = [
        'menu_id',
        'role_id',
        'permission_id',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
