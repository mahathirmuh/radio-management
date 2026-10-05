<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;

class RadioDepartment extends Model
{
    protected $table = 'radio_departments';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function radios()
    {
        return $this->hasMany(Radio::class, 'department_id');
    }
}
