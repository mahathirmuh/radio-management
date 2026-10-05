<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;

/** A company that owns or uses radios (MTI, MMS, SJS, BMS, ...). */
class RadioCompany extends Model
{
    protected $table = 'radio_companies';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function usedRadios()
    {
        return $this->hasMany(Radio::class, 'company_id');
    }

    public function ownedRadios()
    {
        return $this->hasMany(Radio::class, 'owner_company_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->name ? "{$this->code} - {$this->name}" : $this->code;
    }
}
