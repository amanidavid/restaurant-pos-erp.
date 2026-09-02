<?php

namespace App\Models\Landlord;

class TenantBackupPolicy extends LandlordModel
{
    protected function casts(): array
    {
        return ['schedule' => 'array', 'retention' => 'array', 'is_active' => 'boolean', 'next_run_at' => 'immutable_datetime'];
    }
}
