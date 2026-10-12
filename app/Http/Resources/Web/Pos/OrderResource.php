<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid, 'order_number' => $this->order_number,
            'status' => $this->status, 'payment_status' => $this->paymentStatus(), 'created_at' => $this->created_at,
            'guest_count' => $this->guest_count, 'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount, 'service_charge_amount' => $this->service_charge_amount,
            'tax_amount' => $this->tax_amount, 'tip_amount' => $this->tip_amount,
            'total_amount' => $this->total_amount, 'paid_amount' => $this->paid_amount,
            'notes' => $this->notes, 'opened_at' => $this->opened_at, 'closed_at' => $this->closed_at,
            'sales_channel' => $this->relation('salesChannel'), 'service_type' => $this->relation('serviceType'),
            'counter' => $this->relation('counter'), 'dining_table' => $this->relation('diningTable'),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer
                ? ['uuid' => $this->customer->uuid, 'name' => $this->customer->name]
                : ['name' => 'Guest']),
            'items_count' => $this->when(isset($this->items_count), (int) $this->items_count),
            'items_preview' => $this->whenLoaded('itemsPreview', fn () => OrderItemResource::collection($this->itemsPreview)),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }

    private function paymentStatus(): string
    {
        $paid = (float) $this->paid_amount;
        $total = (float) $this->total_amount;

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    private function relation(string $name): mixed
    {
        return $this->whenLoaded($name, fn () => $this->{$name} ? ['uuid' => $this->{$name}->uuid, 'name' => $this->{$name}->name] : null);
    }
}
