<?php

namespace App\Http\Resources\Web\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'grace_minutes' => $this->grace_minutes,
            'crosses_midnight' => $this->crosses_midnight,
            'status' => $this->status,
        ];
    }
}
