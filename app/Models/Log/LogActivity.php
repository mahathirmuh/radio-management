<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

class LogActivity extends Model
{
    protected $guarded = ['id'];
    protected $table = 'activity_logs';
}
