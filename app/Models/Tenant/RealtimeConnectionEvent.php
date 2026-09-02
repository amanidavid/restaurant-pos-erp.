<?php

namespace App\Models\Tenant;

class RealtimeConnectionEvent extends TenantModel
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
