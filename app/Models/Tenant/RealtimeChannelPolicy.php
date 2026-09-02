<?php

namespace App\Models\Tenant;

class RealtimeChannelPolicy extends TenantModel
{
    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_active' => 'boolean'];
    }
}
