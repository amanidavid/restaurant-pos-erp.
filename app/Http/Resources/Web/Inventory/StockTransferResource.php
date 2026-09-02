<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'transfer_number' => $this->transfer_number,
            'status' => $this->status,
            'requested_on' => $this->requested_on?->toDateString(),
            'dispatched_on' => $this->dispatched_on?->toDateString(),
            'received_on' => $this->received_on?->toDateString(),
            'notes' => $this->notes,
            'from_branch' => $this->whenLoaded('fromBranch', fn (): ?array => $this->fromBranch ? [
                'uuid' => $this->fromBranch->uuid,
                'name' => $this->fromBranch->name,
            ] : null),
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn (): ?array => $this->fromWarehouse ? [
                'uuid' => $this->fromWarehouse->uuid,
                'name' => $this->fromWarehouse->name,
                'type' => $this->fromWarehouse->type,
            ] : null),
            'to_branch' => $this->whenLoaded('toBranch', fn (): ?array => $this->toBranch ? [
                'uuid' => $this->toBranch->uuid,
                'name' => $this->toBranch->name,
            ] : null),
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn (): ?array => $this->toWarehouse ? [
                'uuid' => $this->toWarehouse->uuid,
                'name' => $this->toWarehouse->name,
                'type' => $this->toWarehouse->type,
            ] : null),
            'requested_by' => $this->whenLoaded('requestedBy', fn (): ?array => $this->requestedBy ? [
                'uuid' => $this->requestedBy->uuid,
                'display_name' => $this->requestedBy->display_name,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'uuid' => $this->approvedBy->uuid,
                'display_name' => $this->approvedBy->display_name,
            ] : null),
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
