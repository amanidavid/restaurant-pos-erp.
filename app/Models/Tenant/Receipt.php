<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends TenantModel
{
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'issued_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function numberSequence(): BelongsTo
    {
        return $this->belongsTo(NumberSequence::class);
    }

    public function issuedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'issued_by_tenant_user_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function taxTransactions(): HasMany
    {
        return $this->hasMany(TaxTransaction::class);
    }

    public function fiscalReceipts(): HasMany
    {
        return $this->hasMany(FiscalReceipt::class);
    }
}
