<?php

namespace App\Models\Tenant;

class KpiSnapshot extends TenantModel
{
    protected function casts(): array
    {
        return ['snapshot_date' => 'immutable_date', 'metric_value' => 'decimal:4', 'metadata' => 'array'];
    }
}
