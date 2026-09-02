<?php

namespace App\Http\Requests\Web\Organisation;

class UpdateDepartmentRequest extends StoreDepartmentRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['name' => ['sometimes', 'required', 'string', 'max:140', self::SAFE_LABEL_REGEX]]);
    }
}
