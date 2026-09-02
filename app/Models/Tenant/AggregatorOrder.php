<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AggregatorOrder extends TenantModel
{
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'received_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AggregatorOrderEvent::class);
    }
}
