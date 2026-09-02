<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalReceipt extends TenantModel
{
    protected function casts(): array
    {
        return [
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function fiscalDevice(): BelongsTo
    {
        return $this->belongsTo(FiscalDevice::class);
    }

    public function taxRegistration(): BelongsTo
    {
        return $this->belongsTo(TaxRegistration::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'submitted_by_tenant_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FiscalReceiptLine::class);
    }
}
