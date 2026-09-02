<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class SyncMenuItemModifiersRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modifier_groups' => ['required', 'array', 'max:30'],
            'modifier_groups.*.modifier_group_uuid' => ['required', 'uuid', 'distinct'],
            'modifier_groups.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'modifier_groups.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'modifier_groups.required' => 'Please provide modifier groups.',
            'modifier_groups.max' => 'A menu item can have at most 30 modifier groups.',
            'modifier_groups.*.modifier_group_uuid.distinct' => 'Each modifier group can appear only once.',
        ];
    }
}
