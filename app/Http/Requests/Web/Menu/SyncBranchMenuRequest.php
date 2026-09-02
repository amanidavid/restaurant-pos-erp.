<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class SyncBranchMenuRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'max:500'],
            'items.*.menu_item_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.kitchen_section_uuid' => ['nullable', 'uuid'],
            'items.*.price' => ['required', 'decimal:0,2', 'min:0'],
            'items.*.cost_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'items.*.preparation_minutes' => ['nullable', 'integer', 'min:0'],
            'items.*.is_available' => ['required', 'boolean'],
            'items.*.is_visible' => ['required', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'items.required' => 'Please provide branch menu items.',
            'items.max' => 'You can update at most 500 branch menu items at once.',
            'items.*.menu_item_uuid.distinct' => 'Each menu item can appear only once.',
        ];
    }
}
