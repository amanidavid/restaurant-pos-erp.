<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCodeAccount extends TenantModel
{
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function outputTaxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'output_tax_account_id');
    }

    public function inputTaxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'input_tax_account_id');
    }

    public function taxExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'tax_expense_account_id');
    }
}
