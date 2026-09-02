<?php

namespace App\Http\Requests\Web\Organisation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantUserJobHistoryRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'branch_uuid' => ['nullable', 'uuid'],
            'department_uuid' => ['nullable', 'uuid'],
            'job_position_uuid' => ['nullable', 'uuid'],
            'employment_type_uuid' => ['nullable', 'uuid'],
            'base_salary_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
