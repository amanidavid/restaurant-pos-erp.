<?php

namespace App\Models\Tenant;

class SecurityEvent extends TenantModel
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
