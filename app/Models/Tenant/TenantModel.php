<?php

namespace App\Models\Tenant;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    use HasPublicUuid;

    protected $connection = 'tenant';

    protected $guarded = ['id'];
}
