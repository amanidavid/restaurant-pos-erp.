<?php

namespace App\Http\Resources\Web\Organisation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploymentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->uuid, 'name' => $this->name, 'description' => $this->description, 'is_salary_based' => (bool) $this->is_salary_based, 'status' => $this->status];
    }
}
