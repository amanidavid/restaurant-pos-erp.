<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class CloseBusinessDateRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'counted_cash_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999999.99'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
