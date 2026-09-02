<?php

namespace App\Http\Resources\Web\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'branch_uuid' => $this->branch?->uuid,
            'parent_uuid' => $this->parent?->uuid,
        ];
    }
}
