<?php

namespace App\Http\Requests\Web\Organisation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:140', self::SAFE_LABEL_REGEX],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_uuid' => ['prohibited'],
            'parent_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge(['name' => Str::title(Str::lower(Str::squish((string) $this->input('name'))))]);
        }
    }
}
