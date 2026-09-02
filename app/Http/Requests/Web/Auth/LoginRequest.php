<?php

namespace App\Http\Requests\Web\Auth;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
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
            'login' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:120'],
            'tenant_uuid' => ['nullable', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:120', self::SAFE_ADDRESS_REGEX],
            'device_type' => ['nullable', 'string', 'max:32', self::SAFE_DEVICE_TYPE_REGEX],
            'branch_uuid' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $login = trim((string) $this->input('login'));

        $this->merge([
            'login' => Str::contains($login, '@')
                ? Str::lower($login)
                : preg_replace('/\s+/', '', $login),
            'device_name' => $this->filled('device_name')
                ? trim((string) $this->input('device_name'))
                : null,
        ]);
    }
}
