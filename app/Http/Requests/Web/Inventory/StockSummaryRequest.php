<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StockSummaryRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:160', self::SAFE_SEARCH_REGEX],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
