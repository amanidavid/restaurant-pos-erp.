<?php

namespace App\Models\Landlord;

class TenantRetentionPolicyAssignment extends LandlordModel
{
    protected function casts(): array
    {
        return ['rules_override' => 'array', 'next_run_at' => 'immutable_datetime', 'last_run_at' => 'immutable_datetime'];
    }
}
