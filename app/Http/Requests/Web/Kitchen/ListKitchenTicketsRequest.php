<?php

namespace App\Http\Requests\Web\Kitchen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListKitchenTicketsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'preparing', 'ready', 'completed', 'cancelled'])],
            'kitchen_section_uuid' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
