<?php

namespace App\Http\Requests\Web\Kitchen;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKitchenTicketItemStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array { return []; }
}
