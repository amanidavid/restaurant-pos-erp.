<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class OpenBusinessDateRequest extends FormRequest
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
            'business_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'opening_float_amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999999.99'],
            'opening_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
