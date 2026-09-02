<?php

namespace App\Models\Landlord;

use App\Modules\Shared\Database\Concerns\HasPublicUuid;
use Spatie\Permission\Models\Role;

class AdminRole extends Role
{
    use HasPublicUuid;

    protected $connection = 'landlord';

    protected $table = 'admin_roles';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }
}
