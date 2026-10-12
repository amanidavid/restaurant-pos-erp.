<?php

namespace App\Http\Requests\Web\Reports;

use Illuminate\Validation\Rule;

class HistoryRequest extends HistoryPageRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'period' => ['sometimes', Rule::in(['today', 'this_week', 'this_month', 'custom'])],
            'from' => ['required_if:period,custom', 'prohibited_unless:period,custom', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'prohibited_unless:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
    }
}
