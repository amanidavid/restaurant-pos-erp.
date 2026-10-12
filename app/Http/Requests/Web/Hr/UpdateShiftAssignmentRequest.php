<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_definition_uuid' => ['sometimes', 'uuid'],
            'valid_from' => ['sometimes', 'date', 'after_or_equal:today'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
