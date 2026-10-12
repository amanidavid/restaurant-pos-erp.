<?php

namespace App\Http\Requests\Web\Organisation;

use App\Http\Requests\Web\Access\StoreTenantUserRequest;
use Illuminate\Validation\Rule;

class StoreStaffUserRequest extends StoreTenantUserRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'branches' => ['prohibited'],
            'role_uuids' => ['required', 'array', 'min:1', 'max:20'],
            'role_uuids.*' => ['required', 'uuid', 'distinct'],
            'employee_number' => ['required', 'string', 'max:40', self::SAFE_REFERENCE_REGEX],
            'employment_type_uuid' => ['nullable', 'uuid'],
            'department_uuid' => ['nullable', 'uuid'],
            'job_position_uuid' => ['nullable', 'uuid'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'hired_on' => ['required', 'date'],
            'confirmed_on' => ['nullable', 'date', 'after_or_equal:hired_on'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'national_id_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'tax_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'nssf_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
            'nhif_number' => ['nullable', 'string', 'max:60', self::SAFE_REFERENCE_REGEX],
        ]);
    }
}
