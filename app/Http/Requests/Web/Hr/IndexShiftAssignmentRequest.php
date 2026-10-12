<?php

namespace App\Http\Requests\Web\Hr;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class IndexShiftAssignmentRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120', self::SAFE_SEARCH_REGEX],
            'tenant_user_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:active,inactive'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
