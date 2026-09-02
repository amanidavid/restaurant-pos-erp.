<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'from_branch_uuid' => ['nullable', 'uuid'],
            'from_warehouse_uuid' => ['required', 'uuid'],
            'to_branch_uuid' => ['required', 'uuid'],
            'to_warehouse_uuid' => ['required', 'uuid'],
            'requested_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.ingredient_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.unit_uuid' => ['nullable', 'uuid'],
            'items.*.quantity_requested' => ['required', 'decimal:0,4', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'items.required' => 'Please provide at least one transfer item.',
            'items.min' => 'Please provide at least one transfer item.',
            'items.max' => 'A stock transfer can have at most 200 items.',
            'items.*.ingredient_uuid.distinct' => 'Each ingredient can appear only once in the transfer.',
        ];
    }
}
