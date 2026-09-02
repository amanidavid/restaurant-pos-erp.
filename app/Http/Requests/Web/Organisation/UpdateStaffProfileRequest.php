<?php

namespace App\Http\Requests\Web\Organisation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffProfileRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_number' => ['nullable', 'string', 'max:40', self::SAFE_REFERENCE_REGEX],
            'employment_type_uuid' => ['nullable', 'uuid'],
            'home_branch_uuid' => ['nullable', 'uuid'],
            'department_uuid' => ['nullable', 'uuid'],
            'job_position_uuid' => ['nullable', 'uuid'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'hired_on' => ['nullable', 'date'],
            'confirmed_on' => ['nullable', 'date', 'after_or_equal:hired_on'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:hired_on'],
            'employment_status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'terminated'])],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'national_id_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'tax_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'nssf_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'nhif_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
        ];
    }
}
