<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'type' => ['sometimes', 'required', 'string', Rule::in(['main', 'kitchen', 'bar', 'transit'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'closed'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge(['name' => Str::title(Str::lower(Str::squish((string) $this->input('name'))))]);
        }
    }
}
