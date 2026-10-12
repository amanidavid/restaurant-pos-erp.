<?php

namespace App\Http\Requests\Web\Hr;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'tenant_user_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:present,absent,leave,off,half_day'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::parse($this->input('date_from'));
            $to = CarbonImmutable::parse($this->input('date_to'));

            if ($from->diffInDays($to) > 92) {
                $validator->errors()->add('date_to', 'Attendance queries are limited to a 93-day period.');
            }
        }];
    }
}
