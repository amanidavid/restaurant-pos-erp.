<?php

namespace App\Models\Tenant;

class BusinessDayCashReconciliation extends TenantModel
{
    protected function casts(): array
    {
        return ['expected_cash_amount' => 'decimal:2', 'counted_cash_amount' => 'decimal:2', 'variance_amount' => 'decimal:2', 'closed_at' => 'immutable_datetime'];
    }
}
