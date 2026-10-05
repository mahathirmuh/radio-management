<?php

namespace App\Models\Log;

use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Model;

class LogError extends Model
{
    protected $guarded = ['id'];
    protected $table = 'error_logs';
}
