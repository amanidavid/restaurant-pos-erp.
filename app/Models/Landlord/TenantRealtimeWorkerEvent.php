<?php

namespace App\Models\Landlord;

class TenantRealtimeWorkerEvent extends LandlordModel
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
