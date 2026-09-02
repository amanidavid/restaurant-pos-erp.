<?php

namespace App\Http\Requests\Web\Inventory;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockAdjustmentRequest extends FormRequest
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
            'warehouse_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'approved', 'posted', 'cancelled'])],
            'reason_type' => ['nullable', 'string', Rule::in(['damage', 'expiry', 'count_variance', 'manual_correction', 'other'])],
            'adjusted_from' => ['nullable', 'date_format:Y-m-d'],
            'adjusted_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:adjusted_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
