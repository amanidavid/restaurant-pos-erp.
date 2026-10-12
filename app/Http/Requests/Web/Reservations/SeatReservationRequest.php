<?php

namespace App\Http\Requests\Web\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class SeatReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_type_uuid' => ['required', 'uuid'],
            'sales_channel_uuid' => ['nullable', 'uuid'],
            'counter_uuid' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
