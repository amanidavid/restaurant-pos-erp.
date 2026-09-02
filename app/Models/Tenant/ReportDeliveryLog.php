<?php

namespace App\Models\Tenant;

class ReportDeliveryLog extends TenantModel
{
    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
