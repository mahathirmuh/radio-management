<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Employee;
 
use App\Models\Organization\Company;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Employee
 *
 * @property int $id
 * @property string $company_id
 * @property string $employee_id
 * @property string $employee_name
 * @property string|null $employee_email
 * @property string|null $employee_division
 * @property string|null $employee_department
 * @property string|null $employee_section
 * @property string|null $employee_position_title
 * @property string|null $employee_grade_interval
 * @property string|null $supervisor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Employee extends Model
{
    protected $table = 'employee';

    protected $fillable = [
        'company_id',
        'employee_id',
        'employee_name',
        'employee_birth_date',
        'employee_email',
        'employee_division',
        'employee_department',
        'employee_section',
        'employee_position_title',
        'employee_grade_interval',
        'employee_grade_level',
        'employee_join_date',
        'employee_valid_to',
        'employee_nationality',
        'supervisor_id',
        'person_id',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_orangehr_code');
    }
}
