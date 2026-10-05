<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Organization;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Contractor
 * 
 * @property int $id
 * @property int|null $site_id
 * @property string $contractor_code
 * @property string $contractor_name
 * @property string $location
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Site|null $site
 *
 * @package App\Models
 */
class Contractor extends Model
{
	protected $table = 'contractor';

	protected $casts = [
		'site_id' => 'int',
		'is_active' => 'int'
	];

	protected $fillable = [
		'site_id',
		'contractor_code',
		'contractor_name',
		'location',
		'is_active'
	];

	public function site()
	{
		return $this->belongsTo(Site::class);
	}
}
