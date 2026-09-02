<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketItem extends TenantModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'started_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
        ];
    }

    public function kitchenTicket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
