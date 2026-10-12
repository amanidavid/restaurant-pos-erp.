<?php

namespace App\Http\Resources\Web\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashAccountHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isClosed = $this->status === 'closed';

        return [
            'uuid' => $this->uuid,
            'business_date' => $this->business_date,
            'status' => $this->status,
            'opening_float_amount' => $this->opening_float_amount,
            'expected_cash_amount' => $isClosed ? $this->expected_cash_amount : null,
            'counted_cash_amount' => $isClosed ? $this->counted_cash_amount : null,
            'variance_amount' => $isClosed ? $this->variance_amount : null,
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
