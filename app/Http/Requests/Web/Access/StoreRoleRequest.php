<?php

namespace App\Http\Requests\Web\Access;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreRoleRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', self::SAFE_SLUG_REGEX],
            'display_name' => ['required', 'string', 'max:180', self::SAFE_LABEL_REGEX],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_uuid' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? Str::lower(trim((string) $this->input('name'))) : null,
            'display_name' => $this->filled('display_name') ? Str::title(trim((string) $this->input('display_name'))) : null,
        ]);
    }
}
