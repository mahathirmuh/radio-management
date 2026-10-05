<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;

class RadioType extends Model
{
    protected $table = 'radio_types';

    protected $fillable = [
        'name',
        'category',
        'power',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function radios()
    {
        return $this->hasMany(Radio::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return config('radio.categories.' . $this->category, $this->category);
    }
}
