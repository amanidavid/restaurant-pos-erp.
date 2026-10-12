<?php

namespace App\Http\Requests\Web\Organisation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('search')) {
            $this->merge(['search' => Str::squish((string) $this->input('search'))]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'search' => ['sometimes', 'string', 'max:140'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
