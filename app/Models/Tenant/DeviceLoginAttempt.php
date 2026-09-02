<?php

namespace App\Models\Tenant;

class DeviceLoginAttempt extends TenantModel
{
    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
