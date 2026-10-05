<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Organization;
use App\Models\Organization\Company;
use App\Models\Organization\Contractor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Site
 *
 * @property int $id
 * @property string $site_code
 * @property string $site_name
 * @property string|null $site_owner
 * @property string $logo
 * @property string $location
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @property Collection|Company[] $companies
 * @property Collection|Contractor[] $contractors
 *
 * @package App\Models
 */
class Site extends Model
{
    use SoftDeletes;
    protected $table = 'site';

    protected $casts = [
        'is_active' => 'int'
    ];

    protected $fillable = [
        'site_code',
        'site_name',
        'site_owner',
        'logo',
        'location',
        'is_active'
    ];

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function contractors()
    {
        return $this->hasMany(Contractor::class);
    }
}
