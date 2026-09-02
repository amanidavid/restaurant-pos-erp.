<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AggregatorOrderEvent extends TenantModel
{
    protected function casts(): array
    {
        return [
            'event_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    public function aggregatorOrder(): BelongsTo
    {
        return $this->belongsTo(AggregatorOrder::class);
    }
}
