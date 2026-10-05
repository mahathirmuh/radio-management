<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Organization;

use App\Models\Employee;
use App\Models\UserManagement\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Company
 *
 * @property int $id
 * @property string $company_code
 * @property string $name
 * @property string $short_name
 * @property string $location
 * @property int|null $ref_industry_category_id
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @property Collection|User[] $users
 *
 * @package App\Models
 */
class Company extends Model
{
    use SoftDeletes;
    protected $table = 'companies';

    protected $casts = [
        'ref_industry_category_id' => 'int',
        'is_active' => 'int'
    ];

    protected $fillable = [
        'tenant_id',
        'company_code',
        'company_orangehr_code',
        'name',
        'short_name',
        'location',
        'ref_industry_category_id',
        'is_active'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    } 
}
