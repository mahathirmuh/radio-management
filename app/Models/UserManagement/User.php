<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\UserManagement;

use App\Models\Organization\Company;
use App\Models\Organization\Site;
use App\Models\WorkflowLevel;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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

    protected $table = 'users';

    /**
     * How an account gets in, as User Management shows it (signInMethod()). Told from two facts, never stored: whether
     * the account has a directory username, and whether it has signed in with Microsoft.
     */
    public const SIGN_IN_LDAP = 'LDAP';
    public const SIGN_IN_MICROSOFT = 'Microsoft';
    public const SIGN_IN_BOTH = 'Both';
    public const SIGN_IN_PENDING = 'Pending';

    /** What each of them means: the sentence of the form and of the list. */
    public const SIGN_IN_MEANINGS = [
        self::SIGN_IN_LDAP => 'Signs in with their Active Directory account. Disabling it in the directory closes this one.',
        self::SIGN_IN_MICROSOFT => 'Signs in through Microsoft only. No directory username is recorded here.',
        self::SIGN_IN_BOTH => 'Has an Active Directory account and has signed in through Microsoft: either one gets in.',
        self::SIGN_IN_PENDING => 'Made for Microsoft sign-in and not used yet. If it stays like this, check that the e-mail is the address Microsoft knows this person by.',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'microsoft_linked_at' => 'datetime',
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
        'ldap_username',
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

    /**
     * Whether the account may sign in. Only '0' in the column means inactive (set in User Management): whatever
     * else an account from before carries there keeps it active.
     */
    public function isActive(): bool
    {
        return (string) $this->active !== '0';
    }

    /**
     * Whether the columns of the last sign-in are there. The code of this folder is live as soon as it is saved, the
     * migration is run by hand: until then nothing is recorded, instead of failing. (Asked once a minute.)
     */
    public static function signInReady(): bool
    {
        return Cache::remember('users-sign-in-columns', 60, fn () => Schema::hasColumn('users', 'last_login_at'));
    }

    /** Notes that the account signed in just now, and how ("Microsoft", "LDAP", "Password", ...), here and in the audit trail. */
    public function recordSignIn(string $method): void
    {
        app(AuditLogger::class)->log(AuditLogger::LOGIN, new: ['Via' => $method], user: $this);

        $now = now();
        $noted = self::signInReady() ? ['last_login_at' => $now, 'last_login_method' => $method] : [];

        // The first sign-in with Microsoft is kept: it is one of the two facts signInMethod() is told from.
        if ($method === self::SIGN_IN_MICROSOFT && self::directoryReady() && $this->microsoft_linked_at === null) {
            $noted['microsoft_linked_at'] = $now;
        }

        if ($noted === []) {
            return;
        }

        // Written past the model: a sign-in is no change of the account (updated_at stays).
        static::query()->whereKey($this->getKey())->toBase()->update($noted);
    }

    /**
     * Whether the columns of the directory sign-in (ldap_username, microsoft_linked_at) are there. As with
     * signInReady(): until their migration has run, nobody is asked for a directory password and nothing is recorded.
     */
    public static function directoryReady(): bool
    {
        return Cache::remember('users-directory-columns', 60, fn () => Schema::hasColumn('users', 'ldap_username'));
    }

    /**
     * Whether the login form checks the password of this account against Active Directory: it has a directory
     * username. Such an account has no password of its own here.
     */
    public function signsInWithDirectory(): bool
    {
        return self::directoryReady() && filled($this->ldap_username);
    }

    /**
     * How this account gets in: LDAP, Microsoft, Both or Pending. An account with neither fact that did get in
     * another way (a password set in User Management) says that way.
     */
    public function signInMethod(): string
    {
        $directory = $this->signsInWithDirectory();
        $microsoft = self::directoryReady() && $this->microsoft_linked_at !== null;

        if ($directory || $microsoft) {
            return $directory ? ($microsoft ? self::SIGN_IN_BOTH : self::SIGN_IN_LDAP) : self::SIGN_IN_MICROSOFT;
        }

        // The last way in, when it is not one of the two that are told from the facts above: "LDAP" of an account that
        // has no directory username any more, or "Microsoft" of one whose address has changed since, says nothing
        // about now. (Before the columns of those facts are there, the last sign-in with Microsoft is all there is.)
        $last = self::signInReady() ? (string) $this->last_login_method : '';
        $told = self::directoryReady() ? [self::SIGN_IN_LDAP, self::SIGN_IN_MICROSOFT] : [self::SIGN_IN_LDAP];

        return $last !== '' && ! in_array($last, $told, true) ? $last : self::SIGN_IN_PENDING;
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
