<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent ? [
                'uuid' => $this->parent->uuid,
                'name' => $this->parent->name,
            ] : null),
        ];
    }
}
