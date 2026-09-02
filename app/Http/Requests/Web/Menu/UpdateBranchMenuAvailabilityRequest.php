<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchMenuAvailabilityRequest extends FormRequest
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
            'items.*.branch_menu_item_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.is_available' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'items.required' => 'Please provide items to update.',
            'items.max' => 'You can update at most 500 availability records at once.',
            'items.*.branch_menu_item_uuid.distinct' => 'Each branch menu item can appear only once.',
        ];
    }
}
