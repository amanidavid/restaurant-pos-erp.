<?php

namespace App\Http\Resources\Web\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tenant_user_uuid' => $this->uuid,
            'display_name' => $this->display_name,
            'employee_number' => $this->employee_number,
            'employment_status' => $this->employment_status,
            'employment_type' => $this->employmentType ? ['uuid' => $this->employmentType->uuid, 'name' => $this->employmentType->name] : null,
            'home_branch' => $this->homeBranch ? ['uuid' => $this->homeBranch->uuid, 'name' => $this->homeBranch->name] : null,
            'department' => $this->department ? ['uuid' => $this->department->uuid, 'name' => $this->department->name] : null,
            'job_position' => $this->jobPosition ? ['uuid' => $this->jobPosition->uuid, 'name' => $this->jobPosition->name] : null,
            'date_of_birth' => $this->date_of_birth,
            'hired_on' => $this->hired_on,
            'confirmed_on' => $this->confirmed_on,
            'ended_on' => $this->ended_on,
            'gender' => $this->gender,
            'national_id_number' => $this->national_id_number,
            'tax_number' => $this->tax_number,
            'nssf_number' => $this->nssf_number,
            'nhif_number' => $this->nhif_number,
        ];
    }
}
