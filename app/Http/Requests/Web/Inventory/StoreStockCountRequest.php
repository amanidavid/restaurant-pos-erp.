<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockCountRequest extends FormRequest
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
            'counted_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.ingredient_uuid' => ['required', 'uuid'],
            'items.*.location_uuid' => ['nullable', 'uuid'],
            'items.*.unit_uuid' => ['nullable', 'uuid'],
            'items.*.counted_quantity' => ['required', 'decimal:0,4', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'items.required' => 'Please provide at least one counted item.',
            'items.min' => 'Please provide at least one counted item.',
            'items.max' => 'A stock count can have at most 300 items.',
        ];
    }
}
