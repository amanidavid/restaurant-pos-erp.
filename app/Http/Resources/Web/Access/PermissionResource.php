<?php

namespace App\Http\Resources\Web\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'is_system' => (bool) $this->is_system,
            'is_protected' => (bool) $this->is_protected,
        ];
    }
}
