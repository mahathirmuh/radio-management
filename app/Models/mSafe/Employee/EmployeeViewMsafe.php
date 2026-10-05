<?php

namespace App\Models\MSafe\Employee;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeViewMsafe extends Model
{


    protected $connection = 'mSafe';
    protected $table = 'dbo.employee_Msafe';
    protected $fillable = [];
}
