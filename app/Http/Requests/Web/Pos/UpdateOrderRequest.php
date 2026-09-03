<?php

namespace App\Http\Requests\Web\Pos;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'service_type_uuid' => ['sometimes', 'uuid'],
            'counter_uuid' => ['sometimes', 'nullable', 'uuid'],
            'dining_table_uuid' => ['sometimes', 'nullable', 'uuid'],
            'customer_uuid' => ['sometimes', 'nullable', 'uuid'],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
