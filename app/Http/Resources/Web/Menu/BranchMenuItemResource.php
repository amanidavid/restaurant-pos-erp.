<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchMenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'preparation_minutes' => $this->preparation_minutes,
            'is_available' => $this->is_available,
            'is_visible' => $this->is_visible,
            'sort_order' => $this->sort_order,
            'branch' => $this->whenLoaded('branch', fn (): ?array => $this->branch ? [
                'uuid' => $this->branch->uuid,
                'name' => $this->branch->name,
            ] : null),
            'menu_item' => $this->whenLoaded('menuItem', fn (): ?array => $this->menuItem ? [
                'uuid' => $this->menuItem->uuid,
                'name' => $this->menuItem->name,
                'is_combo' => $this->menuItem->is_combo,
            ] : null),
            'kitchen_section' => $this->whenLoaded('kitchenSection', fn (): ?array => $this->kitchenSection ? [
                'uuid' => $this->kitchenSection->uuid,
                'name' => $this->kitchenSection->name,
            ] : null),
        ];
    }
}
