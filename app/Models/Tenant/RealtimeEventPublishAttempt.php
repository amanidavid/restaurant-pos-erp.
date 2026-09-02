<?php

namespace App\Models\Tenant;

class RealtimeEventPublishAttempt extends TenantModel
{
    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime', 'response_payload' => 'array'];
    }
}
