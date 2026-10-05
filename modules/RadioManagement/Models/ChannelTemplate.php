<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A codeplug channel template. When `effective_date` is set, every radio on this
 * template programmed before that date is flagged for re-programming.
 */
class ChannelTemplate extends Model
{
    protected $table = 'radio_channel_templates';

    protected $fillable = [
        'name',
        'channels',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function radios()
    {
        return $this->hasMany(Radio::class, 'channel_template_id');
    }
}
