<?php

namespace App\Http\Requests\Web\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'email' => ['sometimes', 'nullable', 'email:filter', 'max:254'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\- ]{6,19}$/'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:60'],
            'customer_group_uuid' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'blocked'])],
        ];
    }
}
