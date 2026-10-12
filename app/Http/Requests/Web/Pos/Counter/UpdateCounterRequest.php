<?php

namespace App\Http\Requests\Web\Pos\Counter;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCounterRequest extends FormRequest
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

        if ($this->has('type')) {
            $this->merge(['type' => Str::lower(Str::squish((string) $this->input('type')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'type' => ['sometimes', 'required', 'string', Rule::in(['pos', 'bar', 'takeaway', 'reception', 'delivery'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
