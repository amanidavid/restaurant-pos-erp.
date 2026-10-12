<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_user_uuid' => ['required', 'uuid'],
            'attendance_date' => ['required', 'date'],
            'shift_definition_uuid' => ['nullable', 'uuid'],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'status' => ['required', Rule::in(['present', 'absent', 'leave', 'off', 'half_day'])],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
