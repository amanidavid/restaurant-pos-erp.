<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreModifierGroupRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', self::SAFE_LABEL_REGEX],
            'description' => ['nullable', 'string', 'max:2000'],
            'min_select' => ['required', 'integer', 'min:0'],
            'max_select' => ['required', 'integer', 'min:0'],
            'is_required' => ['required', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'options' => ['sometimes', 'array', 'max:100'],
            'options.*.name' => ['required_with:options', 'string', 'max:120', self::SAFE_LABEL_REGEX],
            'options.*.tax_code_uuid' => ['nullable', 'uuid'],
            'options.*.price_delta' => ['required_with:options', 'decimal:0,2', 'min:0'],
            'options.*.is_default' => ['nullable', 'boolean'],
            'options.*.is_active' => ['required_with:options', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'options.max' => 'A modifier group can have at most 100 options.',
            'options.*.name.required_with' => 'Each modifier option needs a name.',
            'options.*.price_delta.required_with' => 'Each modifier option needs a price.',
            'options.*.is_active.required_with' => 'Each modifier option needs an active status.',
        ];
    }
}
