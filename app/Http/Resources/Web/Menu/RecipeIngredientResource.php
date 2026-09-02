<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity' => $this->quantity,
            'waste_percentage' => $this->waste_percentage,
            'notes' => $this->notes,
            'sort_order' => $this->sort_order,
            'ingredient' => $this->whenLoaded('ingredient', fn (): ?array => $this->ingredient ? [
                'uuid' => $this->ingredient->uuid,
                'name' => $this->ingredient->name,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'uuid' => $this->unit->uuid,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ] : null),
        ];
    }
}
