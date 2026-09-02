<?php

namespace App\Http\Resources\Web\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_uuid' => $this->resource['tenant']->uuid,
            'status' => $this->resource['tenant']->status,
            'provisioning_run_uuid' => $this->resource['provisioning_run']->uuid,
            'current_step' => $this->resource['provisioning_run']->current_step,
        ];
    }
}
