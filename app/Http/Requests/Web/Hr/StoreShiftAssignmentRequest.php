<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_user_uuid' => ['required', 'uuid'],
            'shift_definition_uuid' => ['required', 'uuid'],
            'valid_from' => ['required', 'date', 'after_or_equal:today'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
