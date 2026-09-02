<?php

namespace App\Http\Resources\Web\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantSelectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'requires_tenant_selection' => true,
            'tenants' => TenantLoginWorkspaceResource::collection($this->resource['tenants']),
        ];
    }
}
