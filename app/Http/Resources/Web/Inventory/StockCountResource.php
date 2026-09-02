<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'count_number' => $this->count_number,
            'status' => $this->status,
            'counted_on' => $this->counted_on?->toDateString(),
            'notes' => $this->notes,
            'branch' => $this->whenLoaded('branch', fn (): ?array => $this->branch ? [
                'uuid' => $this->branch->uuid,
                'name' => $this->branch->name,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn (): ?array => $this->warehouse ? [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
                'type' => $this->warehouse->type,
            ] : null),
            'counted_by' => $this->whenLoaded('countedBy', fn (): ?array => $this->countedBy ? [
                'uuid' => $this->countedBy->uuid,
                'display_name' => $this->countedBy->display_name,
            ] : null),
            'items' => StockCountItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
