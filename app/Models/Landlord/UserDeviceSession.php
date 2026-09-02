<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceSession extends LandlordModel
{
    protected function casts(): array
    {
        return [
            'device_uuid' => 'string',
            'current_jti' => 'string',
            'last_jti' => 'string',
            'is_active' => 'boolean',
            'started_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(TenantAccount::class);
    }
}
