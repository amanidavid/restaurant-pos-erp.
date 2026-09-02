<?php

namespace App\Models\Tenant;

class BranchDevice extends TenantModel
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime', 'registered_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
