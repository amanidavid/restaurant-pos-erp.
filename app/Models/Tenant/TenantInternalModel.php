<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

abstract class TenantInternalModel extends Model
{
    protected $connection = 'tenant';

    protected $guarded = ['id'];
}
