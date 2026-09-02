<?php

namespace App\Models\Landlord;

class TenantRestoreRun extends LandlordModel
{
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
