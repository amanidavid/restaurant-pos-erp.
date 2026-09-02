<?php

namespace App\Models\Tenant;

class TenantUserDeviceSession extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'force_logout_requested' => 'boolean',
            'logged_in_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'logged_out_at' => 'immutable_datetime',
        ];
    }
}
