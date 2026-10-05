<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models\Employee;
 
use App\Models\Organization\Company;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class EmployeeContractor
 * 
 * @property int $id
 * @property string|null $employee_id
 * @property string $name
 * @property string|null $gender
 * @property string|null $birth_place
 * @property Carbon|null $birth_date
 * @property string|null $marital_status
 * @property string|null $blood_type
 * @property string|null $religion
 * @property string|null $phone_number
 * @property string|null $home_phone
 * @property string|null $email
 * @property string|null $identity_number
 * @property string|null $category_person
 * @property string|null $category_nationality
 * @property string|null $nationality
 * @property string $hire_type
 * @property string|null $address
 * @property string|null $district
 * @property string|null $sub_district
 * @property string|null $city
 * @property string|null $province
 * @property string|null $postal_code
 * @property int|null $company_id
 * @property string|null $company_name
 * @property string|null $contractor_id
 * @property string|null $contractor_name
 * @property string|null $project
 * @property string|null $under_contractor
 * @property string|null $status_employee_in_contractor
 * @property string|null $hire_accepted_location
 * @property string|null $level
 * @property string|null $department
 * @property string|null $department_code
 * @property string|null $division
 * @property string|null $division_code
 * @property string|null $position_title
 * @property string|null $position_code
 * @property string|null $position_level
 * @property string|null $grade_interval
 * @property string|null $section
 * @property string|null $education_level
 * @property string|null $education_institution
 * @property string|null $education_field
 * @property Carbon|null $first_join_date
 * @property Carbon|null $join_date
 * @property Carbon|null $valid_to
 * @property int|null $year_in_group
 * @property int|null $year_in_service
 * @property string|null $point_of_hire
 * @property string|null $point_of_origin
 * @property string|null $work_location
 * @property string|null $work_location_category
 * @property string|null $company_office
 * @property string|null $departure_city
 * @property string|null $destination_city
 * @property string|null $transit
 * @property string|null $final_destination
 * @property string|null $NPWP
 * @property string|null $BPJSTK
 * @property string|null $BPJSK
 * @property string|null $bank_account
 * @property int|null $supervisor_id
 * @property string|null $supervisor_name
 * @property string|null $photo_path
 * @property string|null $mess
 * @property string|null $room_number
 * @property string|null $flight_type
 * @property string $data_mark
 * @property string $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Company|null $company
 *
 * @package App\Models
 */
class EmployeeContractor extends Model
{
	use SoftDeletes;
	protected $table = 'employee_contractor';

	protected $casts = [
		'company_id' => 'int',
		'birth_date' => 'datetime',
		'contractor_id' => 'int',
		'contract_start' => 'datetime',
		'contract_end' => 'datetime',
		'first_join_date' => 'datetime',
		'join_date' => 'datetime',
		'valid_to' => 'datetime',
		'year_in_group' => 'int',
		'year_in_service' => 'int',
		'supervisor_id' => 'int'
	];

	protected $fillable = [
		'employee_id',
		'name',
		'mine_permit_id',
		'rfid_number',
		'first_name',
		'last_name',
		'company_id',
		'company_name',
		'responsible_company',
		'responsible_department',
		'identity_number',
		'nik_valid_until',
		'passport_number',
		'passport_valid_until',
		'visa_number',
		'visa_valid_until',
		'itas_number',
		'itas_valid_until',
		'gender',
		'birth_place',
		'birth_date',
		'age',
		'age_category',
		'marital_status',
		'blood_type',
		'religion',
		'phone_number',
		'home_phone',
		'emergency_contact_name',
		'emergency_contact_relation',
		'emergency_contact_phone',
		'email',
		'category_person',
		'category_nationality',
		'nationality',
		'ethnicity',
		'hire_type',
		'address',
		'province_id',
		'province',
		'city_id',
		'city',
		'district_id',
		'district',
		'village_id',
		'village',
		'address_different',
		'province_different_id',
		'province_different',
		'city_different_id',
		'city_different',
		'district_different_id',
		'district_different',
		'village_different_id',
		'village_different',
		'postal_code',
		'user_show_id',
		'contractor_id',
		'contractor_name',
		'business_field',
		'project',
		'type_contractor',
		'under_contractor',
		'status_employee_in_contractor',
		'contract_duration',
		'contract_start',
		'contract_end',
		'hire_accepted_location',
		'level',
		'department',
		'department_code',
		'division',
		'division_code',
		'position_title',
		'position_code',
		'position_level',
		'grade_interval',
		'section',
		'education_level',
		'education_institution',
		'education_field',
		'first_join_date',
		'join_date',
		'valid_to',
		'year_in_group',
		'year_in_service',
		'point_of_hire',
		'point_of_origin',
		'work_location',
		'work_location_category',
		'company_office',
		'departure_city',
		'destination_city',
		'transit',
		'final_destination',
		'NPWP',
		'BPJSTK',
		'BPJSK',
		'bank_account',
		'supervisor_id',
		'supervisor_name',
		'photo_path',
		'leave_roster',
		'work_system',
		'work_hours_per_day',
		'camp_location',
		'mess',
		'room_number',
		'bed_number',
		'flight_type',
		'data_mark',
		'is_active'
	];

	public function company()
	{
		return $this->belongsTo(Company::class);
	}
}
