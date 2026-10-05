<?php

namespace App\Models\OrangeHR\Organization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationView extends Model
{
    protected $connection = 'orangeHr';
    protected $table = 'dbo.master_organization_view';
    protected $fillable = [];
}
