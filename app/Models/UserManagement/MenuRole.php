<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MenuRole
 *
 * @property int $menu_id
 * @property int $role_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Role $role
 * @property Menu $menu
 *
 * @package App\Models
 */
class MenuRole extends Model
{
    protected $table = 'menu_role';
    public $incrementing = false;

    protected $casts = [
        'menu_id' => 'int',
        'role_id' => 'int'
    ];

    protected $fillable = [
        'menu_id',
        'role_id'
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // public function menu()
    // {
    // 	return $this->belongsTo(SubMenu::class, 'menu_id');
    // }
}
