<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessDayCashReconciliation extends TenantModel
{
    protected function casts(): array
    {
        return [
            'opening_float_amount' => 'decimal:2',
            'expected_cash_amount' => 'decimal:2',
            'counted_cash_amount' => 'decimal:2',
            'variance_amount' => 'decimal:2',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function countLines(): HasMany
    {
        return $this->hasMany(CashCountLine::class, 'business_day_cash_reconciliation_id')
            ->orderByDesc('denomination_value');
    }
}
