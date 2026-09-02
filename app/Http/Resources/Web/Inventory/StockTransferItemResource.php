<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity_requested' => $this->quantity_requested,
            'quantity_dispatched' => $this->quantity_dispatched,
            'quantity_received' => $this->quantity_received,
            'ingredient' => $this->whenLoaded('ingredient', fn (): ?array => $this->ingredient ? [
                'uuid' => $this->ingredient->uuid,
                'name' => $this->ingredient->name,
                'sku' => $this->ingredient->sku,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'uuid' => $this->unit->uuid,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ] : null),
        ];
    }
}
