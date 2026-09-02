<?php

namespace App\Models\Tenant;

class BranchDeviceCapability extends TenantModel
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'settings' => 'array'];
    }
}
