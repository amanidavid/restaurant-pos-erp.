<?php

namespace App\Models\Tenant;

class CashCountLine extends TenantModel
{
    protected function casts(): array
    {
        return ['denomination_value' => 'decimal:2', 'quantity' => 'integer', 'line_total' => 'decimal:2'];
    }
}
