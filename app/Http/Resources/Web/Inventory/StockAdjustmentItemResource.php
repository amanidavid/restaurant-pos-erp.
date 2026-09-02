<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity_delta' => $this->quantity_delta,
            'unit_cost' => $this->unit_cost,
            'reason' => $this->reason,
            'ingredient' => $this->whenLoaded('ingredient', fn (): ?array => $this->ingredient ? [
                'uuid' => $this->ingredient->uuid,
                'name' => $this->ingredient->name,
                'sku' => $this->ingredient->sku,
            ] : null),
            'location' => $this->whenLoaded('location', fn (): ?array => $this->location ? [
                'uuid' => $this->location->uuid,
                'name' => $this->location->name,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'uuid' => $this->unit->uuid,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ] : null),
        ];
    }
}
