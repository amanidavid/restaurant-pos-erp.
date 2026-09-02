<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'base_unit' => $this->whenLoaded('baseUnit', fn (): ?array => $this->baseUnit ? [
                'uuid' => $this->baseUnit->uuid,
                'name' => $this->baseUnit->name,
                'symbol' => $this->baseUnit->symbol,
            ] : null),
            'tax_code' => $this->whenLoaded('taxCode', fn (): ?array => $this->taxCode ? [
                'uuid' => $this->taxCode->uuid,
                'name' => $this->taxCode->name,
            ] : null),
        ];
    }
}
