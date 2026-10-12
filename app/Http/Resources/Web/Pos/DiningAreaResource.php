<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningAreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'tables_count' => $this->when(isset($this->dining_tables_count), (int) $this->dining_tables_count),
            'tables' => DiningTableResource::collection($this->whenLoaded('diningTables')),
        ];
    }
}
