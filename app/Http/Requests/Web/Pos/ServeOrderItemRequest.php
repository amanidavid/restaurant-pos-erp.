<?php

namespace App\Http\Requests\Web\Pos;

use Illuminate\Foundation\Http\FormRequest;

class ServeOrderItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
