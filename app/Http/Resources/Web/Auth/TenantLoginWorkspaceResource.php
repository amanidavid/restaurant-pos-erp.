<?php

namespace App\Http\Resources\Web\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantLoginWorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_uuid' => $this->resource->uuid,
            'name' => $this->resource->trading_name,
            'legal_name' => $this->resource->legal_name,
            'status' => $this->resource->status,
            'is_owner' => (bool) $this->resource->is_owner,
        ];
    }
}
