<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'sku' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'menu_category_uuid' => ['nullable', 'uuid'],
            'tax_code_uuid' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'allows_open_price' => ['required', 'boolean'],
            'is_combo' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'branches' => ['required', 'array', 'min:1', 'max:100'],
            'branches.*.branch_uuid' => ['required', 'uuid', 'distinct'],
            'branches.*.kitchen_section_uuid' => ['nullable', 'uuid'],
            'branches.*.price' => ['required', 'decimal:0,2', 'min:0'],
            'branches.*.cost_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'branches.*.preparation_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'branches.*.is_available' => ['required', 'boolean'],
            'branches.*.is_visible' => ['required', 'boolean'],
            'branches.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'branches.required' => 'Please select at least one branch for this menu item.',
            'branches.min' => 'Please select at least one branch for this menu item.',
            'branches.max' => 'A menu item can be assigned to at most 100 branches at once.',
            'branches.*.branch_uuid.distinct' => 'Each branch can appear only once.',
        ];
    }
}
