<?php

namespace App\Http\Requests\Web\Access;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class SyncBranchRolesRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['role_uuids' => ['present', 'array', 'max:30'], 'role_uuids.*' => ['uuid', 'distinct']];
    }
}
