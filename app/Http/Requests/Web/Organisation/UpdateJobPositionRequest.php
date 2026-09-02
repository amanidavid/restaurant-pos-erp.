<?php

namespace App\Http\Requests\Web\Organisation;

class UpdateJobPositionRequest extends StoreJobPositionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['name' => ['sometimes', 'required', 'string', 'max:160', self::SAFE_LABEL_REGEX]]);
    }
}
