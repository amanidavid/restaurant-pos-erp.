<?php

namespace App\Http\Resources\Web\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserJobHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to,
            'base_salary_amount' => $this->base_salary_amount,
            'currency_code' => $this->currency_code,
            'reason' => $this->reason,
            'branch' => $this->branch ? ['uuid' => $this->branch->uuid, 'name' => $this->branch->name] : null,
            'department' => $this->department ? ['uuid' => $this->department->uuid, 'name' => $this->department->name] : null,
            'job_position' => $this->jobPosition ? ['uuid' => $this->jobPosition->uuid, 'name' => $this->jobPosition->name] : null,
            'employment_type' => $this->employmentType ? ['uuid' => $this->employmentType->uuid, 'name' => $this->employmentType->name] : null,
        ];
    }
}
