<?php

namespace App\Http\Requests\Web\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
            'search' => ['nullable', 'string', 'max:180'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
