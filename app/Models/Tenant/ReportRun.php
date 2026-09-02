<?php

namespace App\Models\Tenant;

class ReportRun extends TenantModel
{
    protected function casts(): array
    {
        return ['parameters' => 'array', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
