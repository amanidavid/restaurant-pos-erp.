<?php

namespace App\Http\Requests\Web\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email:filter', 'max:254'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\- ]{6,19}$/'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'customer_group_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
        ];
    }
}
