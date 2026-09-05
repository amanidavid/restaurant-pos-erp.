<?php

namespace App\Http\Requests\Web\Pos;

use Illuminate\Foundation\Http\FormRequest;

class SendOrderToKitchenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
