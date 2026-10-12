<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clock_in_at' => ['sometimes', 'nullable', 'date'],
            'clock_out_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:clock_in_at'],
            'status' => ['sometimes', Rule::in(['present', 'absent', 'leave', 'off', 'half_day'])],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
