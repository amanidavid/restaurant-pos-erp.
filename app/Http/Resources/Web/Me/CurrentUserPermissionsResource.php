<?php

namespace App\Http\Resources\Web\Me;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentUserPermissionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeBranch = $this->resource['active_branch'];

        return [
            'active_branch_uuid' => $activeBranch?->uuid,
            'branch_permissions' => $this->resource['branch_permissions'],
        ];
    }
}
