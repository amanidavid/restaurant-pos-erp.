<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWarehouseRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(['main', 'kitchen', 'bar', 'transit'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'closed'])],
            'search' => ['nullable', 'string', 'max:160', self::SAFE_SEARCH_REGEX],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
