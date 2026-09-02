<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'adjustment_number' => $this->adjustment_number,
            'reason_type' => $this->reason_type,
            'status' => $this->status,
            'adjusted_on' => $this->adjusted_on?->toDateString(),
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
            'requested_by' => $this->whenLoaded('requestedBy', fn (): ?array => $this->requestedBy ? [
                'uuid' => $this->requestedBy->uuid,
                'display_name' => $this->requestedBy->display_name,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'uuid' => $this->approvedBy->uuid,
                'display_name' => $this->approvedBy->display_name,
            ] : null),
            'items' => StockAdjustmentItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
