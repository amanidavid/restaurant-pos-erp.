<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchConfigurationRequest extends FormRequest
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
            'effective_from' => ['required', 'date'],
            'business_day_opens_at' => ['required', 'date_format:H:i'],
            'business_day_closes_at' => ['required', 'date_format:H:i'],
            'allow_negative_stock' => ['required', 'boolean'],
        ];
    }
}
