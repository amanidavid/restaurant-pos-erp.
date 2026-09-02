<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockTransferRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_uuid' => ['nullable', 'uuid'],
            'direction' => ['nullable', 'string', Rule::in(['outgoing', 'incoming'])],
            'status' => ['nullable', 'string', Rule::in(['draft', 'approved', 'dispatched', 'received', 'cancelled'])],
            'requested_from' => ['nullable', 'date_format:Y-m-d'],
            'requested_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:requested_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
