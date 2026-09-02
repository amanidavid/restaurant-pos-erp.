<?php

namespace App\Http\Requests\Web\Access;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceTenantUserBranchAccessRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branches' => ['required', 'array', 'min:1', 'max:100'],
            'branches.*.branch_uuid' => ['required', 'uuid', 'distinct'],
            'branches.*.is_primary' => ['required', 'boolean'],
            'branches.*.valid_from' => ['nullable', 'date'],
            'branches.*.valid_to' => ['nullable', 'date', 'after_or_equal:branches.*.valid_from'],
        ];
    }
}
