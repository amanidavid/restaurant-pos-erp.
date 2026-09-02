<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexBranchRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'closed'])],
            'search' => ['nullable', 'string', 'max:160', self::SAFE_SEARCH_REGEX],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
