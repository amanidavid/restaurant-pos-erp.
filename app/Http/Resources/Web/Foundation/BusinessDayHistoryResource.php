<?php

namespace App\Http\Resources\Web\Foundation;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessDayHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'business_date' => $this->business_date,
            'business_day_number' => $this->business_day_number,
            'status' => $this->status,
            'opened_at' => $this->opened_at ? CarbonImmutable::parse($this->opened_at)->toIso8601String() : null,
            'closed_at' => $this->closed_at ? CarbonImmutable::parse($this->closed_at)->toIso8601String() : null,
            'orders_count' => (int) $this->orders_count,
            'net_sales_amount' => bcadd((string) $this->net_sales_amount, '0', 2),
        ];
    }
}
