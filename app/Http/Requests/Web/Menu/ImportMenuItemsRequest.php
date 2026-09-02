<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ImportMenuItemsRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'branch_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'file.required' => 'Please upload a menu import file.',
            'file.mimes' => 'Menu import file must be CSV or TXT.',
            'file.max' => 'Menu import file must not be larger than 10 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customAttributes(): array
    {
        return [
            'file' => 'menu import file',
        ];
    }
}
