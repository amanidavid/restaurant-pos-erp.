<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnLine extends TenantModel
{
    protected function casts(): array
    {
        return [
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function taxReturnPeriod(): BelongsTo
    {
        return $this->belongsTo(TaxReturnPeriod::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
