<?php

namespace App\Http\Requests\Web\Pos\Dining;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDiningAreaRequest extends FormRequest
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

        if ($this->has('description') && $this->input('description') !== null) {
            $this->merge(['description' => Str::squish((string) $this->input('description'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
