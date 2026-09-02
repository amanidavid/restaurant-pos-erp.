<?php

namespace App\Models\Landlord;

class TenantRealtimeMonitor extends LandlordModel
{
    protected function casts(): array
    {
        return ['last_checked_at' => 'immutable_datetime', 'metrics' => 'array'];
    }
}
