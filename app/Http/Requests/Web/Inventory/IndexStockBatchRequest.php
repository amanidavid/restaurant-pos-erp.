<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockBatchRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(['active', 'consumed', 'expired', 'closed'])],
            'expires_from' => ['nullable', 'date_format:Y-m-d'],
            'expires_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expires_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
