<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'shift_groups' => ['required', 'array', 'min:1', 'max:20'],
            'shift_groups.*.shift_definition_uuid' => ['required', 'uuid'],
            'shift_groups.*.staff' => ['required', 'array', 'min:1', 'max:100'],
            'shift_groups.*.staff.*.tenant_user_uuid' => ['required', 'uuid'],
            'shift_groups.*.staff.*.date_ranges' => ['required', 'array', 'min:1', 'max:24'],
            'shift_groups.*.staff.*.date_ranges.*.valid_from' => ['required', 'date', 'after_or_equal:today'],
            'shift_groups.*.staff.*.date_ranges.*.valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
