<?php

namespace App\Http\Requests\Web\Menu;

use App\Http\Requests\Web\Menu\Concerns\HasMenuValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UploadMenuItemImageRequest extends FormRequest
{
    use HasMenuValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'image.required' => 'Please upload a menu image.',
            'image.image' => 'Menu image must be a valid image file.',
            'image.mimes' => 'Menu image must be JPG, PNG, or WEBP.',
            'image.max' => 'Menu image must not be larger than 4 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function customAttributes(): array
    {
        return [
            'image' => 'menu image',
        ];
    }
}
