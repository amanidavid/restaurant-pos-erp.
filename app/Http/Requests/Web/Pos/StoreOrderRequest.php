<?php

namespace App\Http\Requests\Web\Pos;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use App\Http\Requests\Web\Pos\Concerns\HasOrderItemRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    use HasApiValidationMessages;
    use HasOrderItemRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_channel_uuid' => ['required', 'uuid'],
            'service_type_uuid' => ['required', 'uuid'],
            'counter_uuid' => ['nullable', 'uuid'],
            'dining_table_uuid' => ['nullable', 'uuid'],
            'customer_uuid' => ['nullable', 'uuid'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + $this->orderItemRules();
    }
}
