<?php

namespace App\Http\Resources\Web\Foundation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessDateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'branch_uuid' => $this->whenLoaded('branch', fn () => $this->branch->uuid),
            'business_date' => $this->business_date?->toDateString(),
            'business_day_number' => $this->business_day_number,
            'status' => $this->status,
            'planned_open_at' => $this->planned_open_at?->toIso8601String(),
            'planned_close_at' => $this->planned_close_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opening_float_amount' => $this->opening_float_amount,
            'expected_cash_amount' => $this->expected_cash_amount,
            'counted_cash_amount' => $this->counted_cash_amount,
            'closing_variance_amount' => $this->closing_variance_amount,
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
        ];
    }
}
