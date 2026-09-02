<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class IndexStockMovementRequest extends FormRequest
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
            'warehouse_uuid' => ['nullable', 'uuid'],
            'ingredient_uuid' => ['nullable', 'uuid'],
            'movement_type_uuid' => ['nullable', 'uuid'],
            'moved_from' => ['nullable', 'date_format:Y-m-d'],
            'moved_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:moved_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
