<?php

namespace App\Http\Requests\Web\Pos\Dining;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name')
                ? Str::title(Str::lower(Str::squish((string) $this->input('name'))))
                : null,
            'shape' => $this->filled('shape')
                ? Str::lower(Str::squish((string) $this->input('shape')))
                : $this->input('shape'),
        ]);
    }

    public function rules(): array
    {
        return [
            'dining_area_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'shape' => ['nullable', 'string', 'max:24', self::SAFE_LABEL_REGEX],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
