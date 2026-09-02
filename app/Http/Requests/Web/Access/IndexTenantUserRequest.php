<?php

namespace App\Http\Requests\Web\Access;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexTenantUserRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['active', 'suspended', 'ended'])],
            'search' => ['nullable', 'string', 'max:180', self::SAFE_SEARCH_REGEX],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('search')) {
            $this->merge([
                'search' => Str::title(Str::lower(Str::squish((string) $this->input('search')))),
            ]);
        }
    }
}
