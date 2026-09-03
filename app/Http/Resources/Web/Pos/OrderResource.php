<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid, 'order_number' => $this->order_number, 'status' => $this->status,
            'guest_count' => $this->guest_count, 'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount, 'service_charge_amount' => $this->service_charge_amount,
            'tax_amount' => $this->tax_amount, 'tip_amount' => $this->tip_amount,
            'total_amount' => $this->total_amount, 'paid_amount' => $this->paid_amount,
            'notes' => $this->notes, 'opened_at' => $this->opened_at, 'closed_at' => $this->closed_at,
            'sales_channel' => $this->relation('salesChannel'), 'service_type' => $this->relation('serviceType'),
            'counter' => $this->relation('counter'), 'dining_table' => $this->relation('diningTable'),
            'customer' => $this->relation('customer'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }

    private function relation(string $name): mixed
    {
        return $this->whenLoaded($name, fn () => $this->{$name} ? ['uuid' => $this->{$name}->uuid, 'name' => $this->{$name}->name] : null);
    }
}
