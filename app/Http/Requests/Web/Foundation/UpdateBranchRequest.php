<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:160', self::SAFE_BUSINESS_NAME_REGEX],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(?:\+?255|0)?[67]\d{8}$/'],
            'address_line_1' => ['nullable', 'string', 'max:180', self::SAFE_ADDRESS_REGEX],
            'address_line_2' => ['nullable', 'string', 'max:180', self::SAFE_ADDRESS_REGEX],
            'city' => ['nullable', 'string', 'max:100', self::SAFE_NAME_REGEX],
            'region' => ['nullable', 'string', 'max:100', self::SAFE_NAME_REGEX],
            'postal_code' => ['nullable', 'string', 'max:20', self::SAFE_REFERENCE_REGEX],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'opened_on' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'closed'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->filled('name')) {
            $data['name'] = Str::title(Str::lower(trim((string) $this->input('name'))));
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
}
