<?php

namespace App\Http\Requests\Web\Inventory;

class UpdateStockTransferRequest extends StoreStockTransferRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_branch_uuid' => ['sometimes', 'required', 'uuid'],
            'from_warehouse_uuid' => ['sometimes', 'required', 'uuid'],
            'to_warehouse_uuid' => ['sometimes', 'required', 'uuid'],
            'requested_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'min:1', 'max:200'],
            'items.*.ingredient_uuid' => ['required_with:items', 'uuid', 'distinct'],
            'items.*.unit_uuid' => ['nullable', 'uuid'],
            'items.*.quantity_requested' => ['required_with:items', 'decimal:0,4', 'gt:0'],
        ];
    }
}
