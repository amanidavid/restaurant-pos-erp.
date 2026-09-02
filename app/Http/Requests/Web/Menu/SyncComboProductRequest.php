<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class SyncComboProductRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.component_menu_item_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.is_required' => ['required', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'items.required' => 'Please provide combo components.',
            'items.min' => 'A combo needs at least one component.',
            'items.max' => 'A combo can have at most 100 components.',
            'items.*.component_menu_item_uuid.distinct' => 'Each combo component can appear only once.',
        ];
    }
}
