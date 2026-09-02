<?php

namespace App\Models\Tenant;

class AuditPolicy extends TenantModel
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'included_events' => 'array', 'excluded_fields' => 'array'];
    }
}
