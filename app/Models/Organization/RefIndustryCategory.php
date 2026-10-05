<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Organization;
 
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class RefIndustryCategory
 * 
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Company[] $companies
 *
 * @package App\Models
 */
class RefIndustryCategory extends Model
{
	use SoftDeletes;
	protected $table = 'ref_industry_categories';

	protected $fillable = [
		'name'
	];

	public function companies()
	{
		return $this->hasMany(Company::class);
	}
}
