<?php

namespace App\Models\Tenant;

class ConfigurationSnapshot extends TenantModel
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'created_for_restore' => 'boolean'];
    }
}
