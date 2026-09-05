<?php

namespace App\Http\Resources\Web\Kitchen;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'ticket_number' => $this->ticket_number,
            'status' => $this->status,
            'opened_at' => $this->opened_at,
            'completed_at' => $this->completed_at,
            'kitchen_section' => $this->whenLoaded('kitchenSection', fn () => ['uuid' => $this->kitchenSection?->uuid, 'name' => $this->kitchenSection?->name]),
            'order' => $this->whenLoaded('order', fn () => ['uuid' => $this->order?->uuid, 'order_number' => $this->order?->order_number, 'status' => $this->order?->status]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'uuid' => $item->uuid, 'order_item_uuid' => $item->orderItem?->uuid,
                'name' => $item->item_name_snapshot, 'quantity' => $item->quantity,
                'status' => $item->status, 'started_at' => $item->started_at, 'ready_at' => $item->ready_at,
            ])->values()),
        ];
    }
}
