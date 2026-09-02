<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCountItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'system_quantity' => $this->system_quantity,
            'counted_quantity' => $this->counted_quantity,
            'variance_quantity' => $this->variance_quantity,
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
