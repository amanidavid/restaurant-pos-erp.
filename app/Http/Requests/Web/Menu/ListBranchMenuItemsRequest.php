<?php

namespace App\Http\Requests\Web\Menu;

use Illuminate\Foundation\Http\FormRequest;

class ListBranchMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'menu_category_uuid' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
