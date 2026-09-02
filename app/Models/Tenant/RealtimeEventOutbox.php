<?php

namespace App\Models\Tenant;

class RealtimeEventOutbox extends TenantModel
{
    protected $table = 'realtime_event_outbox';

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }
}
