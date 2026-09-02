<?php

namespace App\Models\Tenant;

class TenantUserPreference extends TenantModel
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
