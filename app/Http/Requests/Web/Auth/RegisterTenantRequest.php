<?php

namespace App\Http\Requests\Web\Auth;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use App\Models\Landlord\User;
use App\Support\TanzaniaPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterTenantRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'max:180', self::SAFE_BUSINESS_NAME_REGEX],
            'first_name' => ['required', 'string', 'max:80', self::SAFE_NAME_REGEX],
            'last_name' => ['required', 'string', 'max:80', self::SAFE_NAME_REGEX],
            'email' => [
                'nullable',
                'required_without:phone',
                'email:rfc',
                'max:254',
                Rule::unique(User::class, 'email'),
            ],
            'phone' => [
                'nullable',
                'required_without:email',
                'string',
                'max:20',
                'regex:/^\+255[67]\d{8}$/',
                Rule::unique(User::class, 'phone'),
            ],
            'password' => ['required', 'string', 'confirmed', 'min:8', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid Tanzanian phone number.',
            'email.unique' => 'An account already exists with this email address. Please sign in instead.',
            'phone.unique' => 'An account already exists with this phone number. Please sign in instead.',
            'email.required_without' => 'Please provide either an email address or phone number.',
            'phone.required_without' => 'Please provide either a phone number or email address.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'business_name' => $this->normalizeName($this->input('business_name')),
            'first_name' => $this->normalizeName($this->input('first_name')),
            'last_name' => $this->normalizeName($this->input('last_name')),
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'phone' => TanzaniaPhoneNumber::normalize($this->input('phone')),
        ]);
    }

    private function normalizeName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value === '' ? null : Str::title(Str::lower($value));
    }
}
