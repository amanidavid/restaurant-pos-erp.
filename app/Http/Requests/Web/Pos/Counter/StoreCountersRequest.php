<?php

namespace App\Http\Requests\Web\Pos\Counter;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCountersRequest extends FormRequest
{
    use HasApiValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $counters = collect($this->input('counters', []))
            ->map(function (array $counter): array {
                $counter['name'] = isset($counter['name']) ? Str::title(Str::lower(Str::squish($counter['name']))) : null;
                $counter['type'] = isset($counter['type']) ? Str::lower(Str::squish($counter['type'])) : null;

                return $counter;
            })
            ->all();

        $this->merge(['counters' => $counters]);
    }

    public function rules(): array
    {
        return [
            'counters' => ['required', 'array', 'min:1', 'max:25'],
            'counters.*.name' => ['required', 'string', 'max:160', self::SAFE_LABEL_REGEX],
            'counters.*.type' => ['required', 'string', Rule::in(['pos', 'bar', 'takeaway', 'reception', 'delivery'])],
            'counters.*.status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
