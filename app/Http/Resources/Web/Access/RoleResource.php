<?php

namespace App\Http\Resources\Web\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'branch_uuid' => $this->branch?->uuid,
            'is_system' => (bool) $this->is_system,
            'is_protected' => (bool) $this->is_protected,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions
                ->map(fn ($permission): array => [
                    'uuid' => $permission->uuid,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                ])->values()),
        ];
    }
}
