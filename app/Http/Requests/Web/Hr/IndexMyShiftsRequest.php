<?php

namespace App\Http\Requests\Web\Hr;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexMyShiftsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 92) {
                $validator->errors()->add('to', 'Shift schedules are limited to a 93-day period.');
            }
        }];
    }
}
