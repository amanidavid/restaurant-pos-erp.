<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderTransaction extends TenantModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_created_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(ProviderReconciliation::class);
    }
}
