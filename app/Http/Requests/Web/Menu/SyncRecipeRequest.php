<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class SyncRecipeRequest extends FormRequest
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
            'yield_quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'yield_unit_uuid' => ['nullable', 'uuid'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.ingredient_uuid' => ['required', 'uuid', 'distinct'],
            'ingredients.*.unit_uuid' => ['nullable', 'uuid'],
            'ingredients.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'ingredients.*.waste_percentage' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'ingredients.*.notes' => ['nullable', 'string', 'max:2000'],
            'ingredients.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'ingredients.required' => 'Please provide recipe ingredients.',
            'ingredients.min' => 'A recipe needs at least one ingredient.',
            'ingredients.max' => 'A recipe can have at most 100 ingredients.',
            'ingredients.*.ingredient_uuid.distinct' => 'Each ingredient can appear only once in the recipe.',
        ];
    }
}
