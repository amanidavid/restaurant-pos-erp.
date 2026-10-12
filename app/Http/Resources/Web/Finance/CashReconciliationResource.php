<?php

namespace App\Http\Resources\Web\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'reconciliation_number' => $this->reconciliation_number,
            'status' => $this->status,
            'cash_account' => $this->whenLoaded('cashAccount', fn () => [
                'uuid' => $this->cashAccount->uuid,
                'name' => $this->cashAccount->name,
                'currency_code' => $this->cashAccount->currency_code,
            ]),
            'opening_float_amount' => $this->opening_float_amount,
            'expected_cash_amount' => $this->expected_cash_amount,
            'counted_cash_amount' => $this->counted_cash_amount,
            'variance_amount' => $this->variance_amount,
            'lines' => $this->whenLoaded('countLines', fn () => $this->countLines->map(fn ($line) => [
                'denomination_value' => $line->denomination_value,
                'quantity' => $line->quantity,
                'line_amount' => $line->line_amount,
            ])->values()->all()),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'notes' => $this->notes,
        ];
    }
}
