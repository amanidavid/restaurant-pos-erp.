<?php

namespace App\Http\Resources\Web\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'cash_account' => $this->whenLoaded('cashAccount', fn () => [
                'uuid' => $this->cashAccount->uuid,
                'name' => $this->cashAccount->name,
                'currency_code' => $this->cashAccount->currency_code,
            ]),
            'business_day_uuid' => $this->whenLoaded('businessDate', fn () => $this->businessDate->uuid),
            'movement_reason' => $this->movement_reason,
            'amount' => $this->amount,
            'moved_at' => $this->moved_at?->toIso8601String(),
            'notes' => $this->notes,
        ];
    }
}
