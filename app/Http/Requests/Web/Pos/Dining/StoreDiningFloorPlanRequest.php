<?php

namespace App\Http\Requests\Web\Pos\Dining;

use App\Http\Requests\Concerns\HasApiValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDiningFloorPlanRequest extends FormRequest
{
    use HasApiValidationMessages;

    protected function prepareForValidation(): void
    {
        $areas = collect($this->input('areas', []))
            ->map(function (array $area): array {
                $area['name'] = isset($area['name']) ? Str::title(Str::lower(Str::squish($area['name']))) : null;
                $area['description'] = isset($area['description']) ? Str::squish($area['description']) : null;
                $area['tables'] = collect($area['tables'] ?? [])
                    ->map(function (array $table): array {
                        $table['name'] = isset($table['name']) ? Str::title(Str::lower(Str::squish($table['name']))) : null;
                        $table['shape'] = isset($table['shape']) ? Str::lower(Str::squish($table['shape'])) : null;

                        return $table;
                    })
                    ->all();

                return $area;
            })
            ->all();

        $this->merge(['areas' => $areas]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'areas' => ['required', 'array', 'min:1', 'max:25'],
            'areas.*.name' => ['required', 'string', 'max:160'],
            'areas.*.description' => ['nullable', 'string', 'max:2000'],
            'areas.*.status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
            'areas.*.tables' => ['sometimes', 'array', 'max:200'],
            'areas.*.tables.*.name' => ['required', 'string', 'max:160'],
            'areas.*.tables.*.capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'areas.*.tables.*.shape' => ['nullable', 'string', 'max:24'],
            'areas.*.tables.*.status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'maintenance'])],
        ];
    }
}
