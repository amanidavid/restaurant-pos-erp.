<?php

namespace App\Http\Resources\Web\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantProvisioningStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $run = $this->resource['provisioning_run'];
        $tenant = $this->resource['tenant'];

        return [
            'tenant_uuid' => $tenant->uuid,
            'tenant_status' => $tenant->status,
            'provisioning_run_uuid' => $run->uuid,
            'status' => $run->status,
            'current_step' => $run->current_step,
            'failure_code' => $run->failure_code,
            'failure_message' => $run->failure_message,
            'requested_at' => $run->requested_at?->toISOString(),
            'started_at' => $run->started_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
            'steps' => $run->steps->map(fn ($step): array => [
                'sequence' => $step->sequence,
                'name' => $step->step_code,
                'status' => $step->status,
                'attempts' => $step->attempts,
                'started_at' => $step->started_at?->toISOString(),
                'completed_at' => $step->completed_at?->toISOString(),
                'failure_message' => $step->failure_message,
            ])->values(),
        ];
    }
}
