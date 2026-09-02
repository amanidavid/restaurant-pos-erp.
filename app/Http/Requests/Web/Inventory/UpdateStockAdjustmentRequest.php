<?php

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Validation\Rule;

class UpdateStockAdjustmentRequest extends StoreStockAdjustmentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_uuid' => ['sometimes', 'required', 'uuid'],
            'reason_type' => ['sometimes', 'required', 'string', Rule::in(['damage', 'expiry', 'count_variance', 'manual_correction', 'other'])],
            'adjusted_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'min:1', 'max:200'],
            'items.*.ingredient_uuid' => ['required_with:items', 'uuid'],
            'items.*.location_uuid' => ['nullable', 'uuid'],
            'items.*.unit_uuid' => ['nullable', 'uuid'],
            'items.*.quantity_delta' => ['required_with:items', 'numeric', 'not_in:0,0.0,0.00,0.0000'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
