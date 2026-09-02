<?php

namespace App\Http\Requests\Web\Access;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class SyncBranchPermissionsRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permission_uuids' => ['present', 'array', 'max:100'], 'permission_uuids.*' => ['uuid', 'distinct']];
    }
}
