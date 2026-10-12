<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use App\Models\Organization\Company;
use App\Models\Organization\Site;
use App\Models\WorkflowLevel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

/**
 * Class User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property int $company_id
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @property Company $company
 * @property Collection|Role[] $roles
 *
 * @package App\Models
 */

/**
 * @method bool guardRole(int $menuId)
 */
class User extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    /** The positions an account can have (User Management asks for one of them), the senior ones first. */
    public const POSITIONS = ['Manager', 'Superintendent', 'Supervisor', 'IT Field', 'IT System Support', 'IT Assistant'];

    protected $table = 'users';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'company_id' => 'int'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verify_key',
        'access_token',
    ];

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'verify_key',
        'company_id',
        'position',
        'password',
        'remember_token',
        'active',
        'mark_user',
        'person_id',
        'access_token',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** Guard User */
    public function roles()
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('id', 'deleted_at')
            ->withTimestamps();
    }


    public function guardRole(int $menuId)
    {
        // Super Admin (role 1) passes every menu guard, as in CheckMenuPermission and @permission.
        // Without this, unticking a User Management menu on that role locks every admin out.
        if ($this->roles->contains('id', 1)) {
            return true;
        }

        return $this->roles->contains(fn ($role) => $role->getMenus($menuId));
    }

    public function getRole()
    {
        return $this->roles()->first();
    }

    /**
     * Route name this user lands on after signing in: the role's "Home Page" menu when the
     * user may open it, otherwise the first menu they may open, otherwise the dashboard.
     * (Every page is gated by menu, so sending everyone to the dashboard would strand a
     * role that was not given that menu on a 403 page.)
     */
    public function homeRoute(): string
    {
        $superAdmin = $this->roles->contains('id', 1);
        $granted = $this->roles->flatMap->menus->pluck('id')->unique();
        $usable = fn (?Menu $menu) => $menu && $menu->route && Route::has($menu->route)
            && ($superAdmin || $granted->contains($menu->id));

        foreach ($this->roles as $role) {
            $home = is_numeric($role->home_page) ? Menu::find((int) $role->home_page) : null;
            if ($usable($home)) {
                return $home->route;
            }
        }

        $first = Menu::query()->whereNotNull('route')->orderBy('order')->orderBy('id')->get()->first($usable);

        return $first?->route ?? 'dashboard.index';
    }

    public function hasMenuPermission(int $menuId, int $permissionId)
    {
        return $this->roles->contains(fn ($role) => $role->hasMenuPermission($menuId, $permissionId));
    }

    public function hasAnyRole(...$roles)
    {
        return $this->roles()->whereIn('name', ...$roles)->exists();
    }

    public function haveCompany($user)
    {
        $func = $user->func_role;
        $companyObject = null;
        switch ($func) {
            case 'ALL':
                $company = Company::get()->pluck('id');
                $companyObject = (object) $company->toArray();
                break;
            case 'HO':
                $setting = $user->setting_role;
                $site = Site::where('site_owner', $setting)->get()->pluck('id');
                if (isset($site)) {
                    $siteArray = $site->toArray();
                    $company = Company::whereIn('site_id', $siteArray)->get()->pluck('id');
                    $companyObject = (object) $company->toArray();
                } else {
                    $companyObject = [];
                }
                break;
            case 'SITE':
                $setting = json_decode($user->setting_role, true);
                if ($setting !== null && json_last_error() === JSON_ERROR_NONE) {
                    $company = Company::whereIn('site_id', $setting)->get()->pluck('id');
                    $companyObject = (object) $company->toArray();
                } else {
                    $companyObject = [];
                }
                break;
            case 'COMPANY':
                $setting = json_decode($user->setting_role, true);
                if ($setting !== null && json_last_error() === JSON_ERROR_NONE) {
                    $company = Company::whereIn('id', $setting)->get()->pluck('id');
                    $companyObject = (object) $company->toArray();
                } else {
                    $companyObject = [];
                }
                break;
        }
        return  $companyObject;
    }

    public function haveCompanyOrangeHr($user)
    {
        $func = $user->func_role;
        $companyObject = null;
        switch ($func) {
            case 'ALL':
                $company = Company::get()->pluck('company_orangehr_code');
                $companyObject =  $company->toArray();
                break;
            case 'HO':
                $setting = $user->setting_role;
                $site = Site::where('site_owner', $setting)->get()->pluck('id');
                if (isset($site)) {
                    $siteArray = $site->toArray();
                    $company = Company::whereIn('site_id', $siteArray)->get()->pluck('company_orangehr_code');
                    $companyObject =  $company->toArray();
                } else {
                    $companyObject = [];
                }
                break;
            case 'SITE':
                $setting = json_decode($user->setting_role, true);
                if ($setting !== null && json_last_error() === JSON_ERROR_NONE) {
                    $company = Company::whereIn('site_id', $setting)->get()->pluck('company_orangehr_code');
                    $companyObject =  $company->toArray();
                } else {
                    $companyObject = [];
                }
                break;
            case 'COMPANY':
                $setting = json_decode($user->setting_role, true);
                if ($setting !== null && json_last_error() === JSON_ERROR_NONE) {
                    $company = Company::whereIn('id', $setting)->get()->pluck('company_orangehr_code');
                    $companyObject =  $company->toArray();
                } else {
                    $companyObject = [];
                }
                break;
        }
        return  $companyObject;
    }

    /*
	Catatan Nanda : Hati-hati, kamu tidak tahu apa yang kamu hapus :)).
	[MCG 2023]
	*/
}
