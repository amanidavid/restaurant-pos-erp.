<?php

namespace App\Models\Tenant;

class ShiftCashReconciliation extends TenantModel
{
    protected function casts(): array
    {
        return ['opening_float_amount' => 'decimal:2', 'expected_cash_amount' => 'decimal:2', 'counted_cash_amount' => 'decimal:2', 'variance_amount' => 'decimal:2', 'closed_at' => 'immutable_datetime'];
    }
}
