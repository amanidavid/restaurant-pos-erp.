<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'starts_at' => ['sometimes', 'date_format:H:i'],
            'ends_at' => ['sometimes', 'date_format:H:i'],
            'grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
