<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboProductItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quantity' => $this->quantity,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
            'component_menu_item' => $this->whenLoaded('componentMenuItem', fn (): ?array => $this->componentMenuItem ? [
                'uuid' => $this->componentMenuItem->uuid,
                'name' => $this->componentMenuItem->name,
                'is_combo' => $this->componentMenuItem->is_combo,
            ] : null),
        ];
    }
}
