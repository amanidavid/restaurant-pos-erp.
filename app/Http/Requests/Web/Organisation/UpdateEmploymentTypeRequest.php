<?php

namespace App\Http\Requests\Web\Organisation;

class UpdateEmploymentTypeRequest extends StoreEmploymentTypeRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', self::SAFE_LABEL_REGEX],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_salary_based' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
