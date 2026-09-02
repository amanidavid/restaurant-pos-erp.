<?php

namespace App\Models\Landlord;

class TenantDatabaseMaintenanceEvent extends LandlordModel
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
