<?php

namespace App\Models\Landlord;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Spatie\Permission\Models\Permission;

class AdminPermission extends Permission
{
    use HasPublicUuid;

    protected $connection = 'landlord';

    protected $table = 'admin_permissions';
}
