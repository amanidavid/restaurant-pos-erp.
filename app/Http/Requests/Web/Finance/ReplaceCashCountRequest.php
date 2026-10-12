<?php

namespace App\Http\Requests\Web\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCashCountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'max:30'],
            'lines.*.denomination_value' => ['required', 'decimal:0,2', 'gt:0', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
