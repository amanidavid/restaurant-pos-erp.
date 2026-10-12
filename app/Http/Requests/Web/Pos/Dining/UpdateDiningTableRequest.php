<?php

namespace App\Http\Requests\Web\Pos\Dining;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDiningTableRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => Str::title(Str::lower(Str::squish((string) $this->input('name'))))]);
        }

        if ($this->has('shape') && $this->input('shape') !== null) {
            $this->merge(['shape' => Str::lower(Str::squish((string) $this->input('shape')))]);
        }
    }

    public function rules(): array
    {
        return [
            'dining_area_uuid' => ['sometimes', 'nullable', 'uuid'],
            'name' => ['sometimes', 'required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'shape' => ['sometimes', 'nullable', 'string', 'max:24', self::SAFE_LABEL_REGEX],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
