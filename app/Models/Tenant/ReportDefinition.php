<?php

namespace App\Models\Tenant;

class ReportDefinition extends TenantModel
{
    protected function casts(): array
    {
        return ['parameters_schema' => 'array', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }
}
