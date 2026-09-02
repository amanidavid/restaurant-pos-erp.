<?php

namespace App\Models\Tenant;

class RetentionCleanupRun extends TenantModel
{
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
