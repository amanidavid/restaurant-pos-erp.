<?php

namespace App\Http\Requests\Web\Inventory;

class UpdateStockCountRequest extends StoreStockCountRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_uuid' => ['sometimes', 'required', 'uuid'],
            'counted_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'min:1', 'max:300'],
            'items.*.ingredient_uuid' => ['required_with:items', 'uuid'],
            'items.*.location_uuid' => ['nullable', 'uuid'],
            'items.*.unit_uuid' => ['nullable', 'uuid'],
            'items.*.counted_quantity' => ['required_with:items', 'decimal:0,4', 'min:0'],
        ];
    }
}
