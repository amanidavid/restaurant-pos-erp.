<?php

namespace App\Models\Landlord;

class TenantRestoreRequest extends LandlordModel
{
    protected function casts(): array
    {
        return ['requested_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }
}
