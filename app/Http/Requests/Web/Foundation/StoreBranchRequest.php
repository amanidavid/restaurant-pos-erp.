<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use App\Http\Requests\Web\Foundation\Concerns\NormalizesBusinessDayTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBranchRequest extends FormRequest
{
    use HasApiValidationMessages;
    use NormalizesBusinessDayTimes;

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
            'name' => ['required', 'string', 'max:160', self::SAFE_BUSINESS_NAME_REGEX],
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
            'configuration' => ['nullable', 'array'],
            'configuration.business_day_opens_at' => ['nullable', 'date_format:H:i'],
            'configuration.business_day_closes_at' => ['nullable', 'date_format:H:i'],
            'configuration.allow_negative_stock' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $configuration = $this->input('configuration');

        $this->merge([
            'name' => $this->filled('name') ? Str::title(Str::lower(trim((string) $this->input('name')))) : $this->input('name'),
            'email' => $this->filled('email') ? Str::lower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? preg_replace('/\s+/', '', (string) $this->input('phone')) : null,
            'configuration' => is_array($configuration)
                ? $this->normalizeBusinessDayTimes($configuration)
                : $configuration,
        ]);
    }
}
