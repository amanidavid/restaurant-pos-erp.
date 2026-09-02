<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalReceiptLine extends TenantModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function fiscalReceipt(): BelongsTo
    {
        return $this->belongsTo(FiscalReceipt::class);
    }

    public function taxTransaction(): BelongsTo
    {
        return $this->belongsTo(TaxTransaction::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
