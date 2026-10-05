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
class MasterDepartment extends Model
{
    use SoftDeletes;
    protected $table = 'master_department';

    protected $fillable = [
        'name',
        'code', 
    ];
 
}
