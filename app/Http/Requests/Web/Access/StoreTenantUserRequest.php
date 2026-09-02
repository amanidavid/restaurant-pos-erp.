<?php

namespace App\Http\Requests\Web\Access;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

class StoreTenantUserRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80', self::SAFE_NAME_REGEX],
            'last_name' => ['required', 'string', 'max:80', self::SAFE_NAME_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:254', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email', 'regex:/^(?:\+?255|0)?[67]\d{8}$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'branches' => ['required', 'array', 'min:1', 'max:100'],
            'branches.*.branch_uuid' => ['required', 'uuid', 'distinct'],
            'branches.*.is_primary' => ['required', 'boolean'],
            'branches.*.valid_from' => ['nullable', 'date'],
            'branches.*.valid_to' => ['nullable', 'date', 'after_or_equal:branches.*.valid_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->filled('first_name') ? Str::title(Str::lower(trim((string) $this->input('first_name')))) : null,
            'last_name' => $this->filled('last_name') ? Str::title(Str::lower(trim((string) $this->input('last_name')))) : null,
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? preg_replace('/\s+/', '', (string) $this->input('phone')) : null,
        ]);
    }
}
