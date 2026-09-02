<?php

namespace App\Models\Tenant;

class DevicePairingRequest extends TenantModel
{
    protected function casts(): array
    {
        return ['requested_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }
}
