<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'batch_number' => $this->batch_number,
            'received_on' => $this->received_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'quantity_on_hand' => $this->quantity_on_hand,
            'unit_cost' => $this->unit_cost,
            'status' => $this->status,
            'ingredient' => $this->whenLoaded('ingredient', fn (): ?array => $this->ingredient ? [
                'uuid' => $this->ingredient->uuid,
                'name' => $this->ingredient->name,
                'sku' => $this->ingredient->sku,
                'base_unit' => $this->ingredient->relationLoaded('baseUnit') && $this->ingredient->baseUnit ? [
                    'uuid' => $this->ingredient->baseUnit->uuid,
                    'name' => $this->ingredient->baseUnit->name,
                    'symbol' => $this->ingredient->baseUnit->symbol,
                ] : null,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn (): ?array => $this->warehouse ? [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
                'branch' => $this->warehouse->relationLoaded('branch') && $this->warehouse->branch ? [
                    'uuid' => $this->warehouse->branch->uuid,
                    'name' => $this->warehouse->branch->name,
                ] : null,
            ] : null),
            'location' => $this->whenLoaded('location', fn (): ?array => $this->location ? [
                'uuid' => $this->location->uuid,
                'name' => $this->location->name,
            ] : null),
        ];
    }
}
