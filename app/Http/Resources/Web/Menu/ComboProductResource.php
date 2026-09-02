<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'is_active' => $this->is_active,
            'menu_item' => $this->whenLoaded('menuItem', fn (): ?array => $this->menuItem ? [
                'uuid' => $this->menuItem->uuid,
                'name' => $this->menuItem->name,
            ] : null),
            'items' => ComboProductItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
