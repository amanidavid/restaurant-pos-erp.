<?php

namespace App\Http\Requests\Web\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_day_uuid' => ['required', 'uuid'],
            'movement_reason' => ['required', Rule::in(['cash_in', 'cash_out', 'safe_drop'])],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999999.99'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
