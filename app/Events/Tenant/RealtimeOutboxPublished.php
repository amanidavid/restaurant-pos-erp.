<?php

namespace App\Events\Tenant;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class RealtimeOutboxPublished implements ShouldBroadcastNow
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $channelName,
        private readonly string $eventType,
        private readonly string $eventUuid,
        private readonly array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channelName);
    }

    public function broadcastAs(): string
    {
        return 'realtime.event';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event_uuid' => $this->eventUuid,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
        ];
    }
}
