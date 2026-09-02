<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryEvent extends TenantModel
{
    protected function casts(): array
    {
        return [
            'event_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    public function notificationDelivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class);
    }
}
