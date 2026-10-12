<?php

namespace App\Http\Requests\Web\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tenant_user_uuids' => ['required', 'array', 'min:1', 'max:100'],
            'tenant_user_uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
