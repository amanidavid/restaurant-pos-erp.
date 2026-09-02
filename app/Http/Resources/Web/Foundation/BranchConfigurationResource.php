<?php

namespace App\Http\Resources\Web\Foundation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'version' => $this->version,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'business_day_opens_at' => $this->business_day_opens_at,
            'business_day_closes_at' => $this->business_day_closes_at,
            'allow_negative_stock' => (bool) $this->allow_negative_stock,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
