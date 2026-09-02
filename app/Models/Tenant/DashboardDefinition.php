<?php

namespace App\Models\Tenant;

class DashboardDefinition extends TenantModel
{
    protected function casts(): array
    {
        return ['layout' => 'array', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }
}
