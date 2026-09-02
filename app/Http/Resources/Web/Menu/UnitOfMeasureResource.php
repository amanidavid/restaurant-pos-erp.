<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOfMeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'unit_type' => $this->unit_type,
            'base_factor' => $this->base_factor,
            'is_base_unit' => $this->is_base_unit,
            'status' => $this->status,
        ];
    }
}
