<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'yield_quantity' => $this->yield_quantity,
            'is_active' => $this->is_active,
            'yield_unit' => $this->whenLoaded('yieldUnit', fn (): ?array => $this->yieldUnit ? [
                'uuid' => $this->yieldUnit->uuid,
                'name' => $this->yieldUnit->name,
                'symbol' => $this->yieldUnit->symbol,
            ] : null),
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('ingredients')),
        ];
    }
}
