<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationDelivery extends TenantModel
{
    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'provider_response' => 'array',
        ];
    }

    public function notificationMessage(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class);
    }

    public function notificationRecipient(): BelongsTo
    {
        return $this->belongsTo(NotificationRecipient::class);
    }

    public function integrationAccount(): BelongsTo
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NotificationDeliveryEvent::class);
    }
}
