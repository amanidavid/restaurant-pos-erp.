<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateCompanyProfileRequest extends FormRequest
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
            'legal_name' => ['sometimes', 'required', 'string', 'max:180', self::SAFE_BUSINESS_NAME_REGEX],
            'trading_name' => ['sometimes', 'required', 'string', 'max:180', self::SAFE_BUSINESS_NAME_REGEX],
            'tin' => ['nullable', 'string', 'max:40', self::SAFE_REFERENCE_REGEX],
            'vrn' => ['nullable', 'string', 'max:40', self::SAFE_REFERENCE_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(?:\+?255|0)?[67]\d{8}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('legal_name')) {
            $data['legal_name'] = $this->normalizeName($this->input('legal_name'));
        }

        if ($this->has('trading_name')) {
            $data['trading_name'] = $this->normalizeName($this->input('trading_name'));
        }

        if ($this->filled('email')) {
            $data['email'] = Str::lower(trim((string) $this->input('email')));
        }

        if ($this->filled('phone')) {
            $data['phone'] = preg_replace('/\s+/', '', (string) $this->input('phone'));
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    private function normalizeName(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value === '' ? null : Str::title(Str::lower($value));
    }
}
