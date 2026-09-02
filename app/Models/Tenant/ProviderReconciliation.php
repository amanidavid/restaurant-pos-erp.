<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderReconciliation extends TenantModel
{
    protected function casts(): array
    {
        return [
            'provider_amount' => 'decimal:2',
            'internal_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
        ];
    }

    public function providerSettlement(): BelongsTo
    {
        return $this->belongsTo(ProviderSettlement::class);
    }

    public function providerTransaction(): BelongsTo
    {
        return $this->belongsTo(ProviderTransaction::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
