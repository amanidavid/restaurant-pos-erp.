<?php

namespace App\Http\Requests\Web\Pos;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use App\Http\Requests\Web\Pos\Concerns\HasOrderItemRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemRequest extends FormRequest
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
            'branch_menu_item_uuid' => ['prohibited'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ] + $this->modifierRules('modifiers');
    }
}
