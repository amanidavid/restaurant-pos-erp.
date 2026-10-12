<?php

namespace App\Http\Requests\Web\Pos;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrdersRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['draft', 'open', 'sent_to_kitchen', 'partially_ready', 'ready', 'served', 'closed', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:160', self::SAFE_SEARCH_REGEX],
            'month_from' => ['nullable', 'date_format:Y-m'],
            'month_to' => ['nullable', 'date_format:Y-m', 'after_or_equal:month_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
