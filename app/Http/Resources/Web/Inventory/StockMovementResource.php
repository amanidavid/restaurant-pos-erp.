<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'moved_at' => $this->moved_at?->toISOString(),
            'notes' => $this->notes,
            'movement_type' => $this->whenLoaded('stockMovementType', fn (): ?array => $this->stockMovementType ? [
                'uuid' => $this->stockMovementType->uuid,
                'name' => $this->stockMovementType->name,
                'direction' => $this->stockMovementType->direction,
            ] : null),
            'ingredient' => $this->whenLoaded('ingredient', fn (): ?array => $this->ingredient ? [
                'uuid' => $this->ingredient->uuid,
                'name' => $this->ingredient->name,
                'sku' => $this->ingredient->sku,
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
            'performed_by' => $this->whenLoaded('performedBy', fn (): ?array => $this->performedBy ? [
                'uuid' => $this->performedBy->uuid,
                'display_name' => $this->performedBy->display_name,
            ] : null),
        ];
    }
}
