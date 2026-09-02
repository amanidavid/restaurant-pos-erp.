<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuItemRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'sku' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'menu_category_uuid' => ['nullable', 'uuid'],
            'tax_code_uuid' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'allows_open_price' => ['sometimes', 'boolean'],
            'is_combo' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
