<?php

namespace App\Http\Requests\Web\Pos;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use App\Http\Requests\Web\Pos\Concerns\HasOrderItemRules;
use Illuminate\Foundation\Http\FormRequest;

class AddOrderItemsRequest extends FormRequest
{
    use HasApiValidationMessages;
    use HasOrderItemRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->orderItemRules();
    }
}
