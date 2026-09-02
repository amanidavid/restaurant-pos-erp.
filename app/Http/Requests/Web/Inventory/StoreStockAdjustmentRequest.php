<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_uuid' => ['nullable', 'uuid'],
            'warehouse_uuid' => ['required', 'uuid'],
            'reason_type' => ['required', 'string', Rule::in(['damage', 'expiry', 'count_variance', 'manual_correction', 'other'])],
            'adjusted_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.ingredient_uuid' => ['required', 'uuid'],
            'items.*.location_uuid' => ['nullable', 'uuid'],
            'items.*.unit_uuid' => ['nullable', 'uuid'],
            'items.*.quantity_delta' => ['required', 'numeric', 'not_in:0,0.0,0.00,0.0000'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'items.required' => 'Please provide at least one adjustment item.',
            'items.min' => 'Please provide at least one adjustment item.',
            'items.max' => 'A stock adjustment can have at most 200 items.',
            'items.*.quantity_delta.not_in' => 'Adjustment quantity cannot be zero.',
        ];
    }
}
