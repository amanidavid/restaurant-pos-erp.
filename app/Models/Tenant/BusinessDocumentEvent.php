<?php

namespace App\Models\Tenant;

class BusinessDocumentEvent extends TenantModel
{
    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
