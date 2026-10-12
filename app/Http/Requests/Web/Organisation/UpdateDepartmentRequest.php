<?php

namespace App\Http\Requests\Web\Organisation;

class UpdateDepartmentRequest extends StoreDepartmentRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:140', self::SAFE_LABEL_REGEX],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parent_uuid' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'in:active,inactive'],
            'branch_uuid' => ['prohibited'],
        ];
    }
}
