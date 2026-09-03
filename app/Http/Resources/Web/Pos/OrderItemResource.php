<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid, 'name' => $this->item_name_snapshot,
            'unit_price' => $this->unit_price_snapshot, 'quantity' => $this->quantity,
            'tax_amount' => $this->tax_amount, 'line_total' => $this->line_total,
            'status' => $this->status, 'notes' => $this->notes,
            'modifiers' => $this->whenLoaded('modifiers', fn () => $this->modifiers->map(fn ($modifier) => [
                'uuid' => $modifier->uuid, 'name' => $modifier->modifier_name_snapshot,
                'price_delta' => $modifier->price_delta_snapshot, 'quantity' => $modifier->quantity,
                'line_total' => $modifier->line_total,
            ])->values()),
        ];
    }
}
