<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One time a signature was put on a form: whose, by whom (always the owner), for which radio, when.
 */
class ItSignatureUse extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'it_signature_uses';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
