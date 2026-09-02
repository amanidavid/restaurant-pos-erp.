<?php

namespace App\Models\Tenant;

class DashboardWidget extends TenantModel
{
    protected function casts(): array
    {
        return ['position' => 'array', 'settings' => 'array', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
