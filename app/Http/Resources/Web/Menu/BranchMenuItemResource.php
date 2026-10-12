<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchMenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Keep uuid for compatibility; use the explicit fields for new clients.
            'uuid' => $this->uuid,
            'branch_menu_item_uuid' => $this->uuid,
            'menu_item_uuid' => $this->whenLoaded('menuItem', fn (): ?string => $this->menuItem?->uuid),
            'menu_category_uuid' => $this->whenLoaded('menuItem', fn (): ?string => $this->menuItem?->menuCategory?->uuid),
            'image_url' => $this->whenLoaded('menuItem', fn (): ?string => $this->menuItem?->image_url),
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
                'menu_category' => $this->menuItem->relationLoaded('menuCategory') && $this->menuItem->menuCategory ? [
                    'uuid' => $this->menuItem->menuCategory->uuid,
                    'name' => $this->menuItem->menuCategory->name,
                ] : null,
                'tax_code' => $this->menuItem->relationLoaded('taxCode') && $this->menuItem->taxCode ? [
                    'uuid' => $this->menuItem->taxCode->uuid,
                    'name' => $this->menuItem->taxCode->name,
                    'rate_percentage' => $this->menuItem->taxCode->rate_percentage,
                    'is_inclusive' => $this->menuItem->taxCode->is_inclusive,
                ] : null,
                'modifier_groups' => $this->menuItem->relationLoaded('modifierGroups')
                    ? ModifierGroupResource::collection(
                        $this->menuItem->modifierGroups->pluck('modifierGroup')->filter()->values()
                    )
                    : [],
                'combo_product' => $this->menuItem->relationLoaded('comboProduct') && $this->menuItem->comboProduct
                    ? new ComboProductResource($this->menuItem->comboProduct)
                    : null,
            ] : null),
            'kitchen_section' => $this->whenLoaded('kitchenSection', fn (): ?array => $this->kitchenSection ? [
                'uuid' => $this->kitchenSection->uuid,
                'name' => $this->kitchenSection->name,
            ] : null),
        ];
    }
}
