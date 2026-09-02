<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'price_delta' => $this->price_delta,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'tax_code' => $this->whenLoaded('taxCode', fn (): ?array => $this->taxCode ? [
                'uuid' => $this->taxCode->uuid,
                'name' => $this->taxCode->name,
                'rate_percentage' => $this->taxCode->rate_percentage,
            ] : null),
        ];
    }
}
