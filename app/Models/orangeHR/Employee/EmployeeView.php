<?php

namespace App\Models\OrangeHR\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeView extends Model
{
    // use HasFactory;

    protected $connection = 'orangeHr';
    protected $table = 'dbo.IT_PMS_employee_datebase_view';
    protected $fillable = [];
}
