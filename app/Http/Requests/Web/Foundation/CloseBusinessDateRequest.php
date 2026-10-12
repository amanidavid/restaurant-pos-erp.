<?php

namespace App\Http\Requests\Web\Foundation;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class CloseBusinessDateRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
