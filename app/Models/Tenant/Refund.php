<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends TenantModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requested_by_tenant_user_id');
    }

    public function approvedByTenantUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approved_by_tenant_user_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
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
