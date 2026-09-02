<?php

namespace App\Models\Tenant;

class DevicePrintAgentSession extends TenantModel
{
    protected function casts(): array
    {
        return ['connected_at' => 'immutable_datetime', 'last_heartbeat_at' => 'immutable_datetime', 'disconnected_at' => 'immutable_datetime'];
    }
}
