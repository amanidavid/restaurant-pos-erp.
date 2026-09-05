<?php

namespace App\Http\Requests\Web\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['payment_method_uuid' => ['required', 'uuid'], 'received_amount' => ['required', 'decimal:0,2', 'gt:0'], 'notes' => ['nullable', 'string', 'max:1000']]; }
}
