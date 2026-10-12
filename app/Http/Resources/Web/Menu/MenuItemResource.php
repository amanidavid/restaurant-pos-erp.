<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'allows_open_price' => $this->allows_open_price,
            'is_combo' => $this->is_combo,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'menu_category' => $this->whenLoaded('menuCategory', fn (): ?array => $this->menuCategory ? [
                'uuid' => $this->menuCategory->uuid,
                'name' => $this->menuCategory->name,
            ] : null),
            'tax_code' => $this->whenLoaded('taxCode', fn (): ?array => $this->taxCode ? [
                'uuid' => $this->taxCode->uuid,
                'name' => $this->taxCode->name,
            ] : null),
            'modifier_groups' => $this->when(
                $this->relationLoaded('modifierGroups'),
                fn () => ModifierGroupResource::collection(
                    $this->modifierGroups->pluck('modifierGroup')->filter()->values()
                ),
            ),
            'branch_menu_items' => BranchMenuItemResource::collection($this->whenLoaded('branchMenuItems')),
        ];
    }
}
