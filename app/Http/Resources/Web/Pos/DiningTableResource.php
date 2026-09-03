<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'shape' => $this->shape,
            'status' => $this->status,
            'dining_area' => $this->whenLoaded('diningArea', fn (): ?array => $this->diningArea ? [
                'uuid' => $this->diningArea->uuid,
                'name' => $this->diningArea->name,
            ] : null),
        ];
    }
}
