<?php

/**
 * Created by Reliese Model.
 */

namespace App\ModelsOrganization\Organization;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Division
 * 
 * @property int $id
 * @property string|null $division
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|App[] $apps
 *
 * @package App\Models
 */
class MasterDivision extends Model
{

	protected $connection = 'employee';
	protected $table = 'division';

	protected $fillable = [
		'division'
	];
}
