<?php

namespace App\Http\Resources\Web\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'name' => $this->name, 'description' => $this->description, 'department_uuid' => $this->department?->uuid, 'level_rank' => $this->level_rank, 'status' => $this->status];
    }
}
