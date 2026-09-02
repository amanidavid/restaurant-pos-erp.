<?php

namespace App\Http\Resources\Web\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'display_name' => $this->display_name,
            'status' => $this->status,
            'user' => [
                'uuid' => $this->landlordIdentity?->uuid,
                'first_name' => $this->landlordIdentity?->first_name,
                'last_name' => $this->landlordIdentity?->last_name,
                'email' => $this->landlordIdentity?->email,
                'phone' => $this->landlordIdentity?->phone,
                'last_login_at' => $this->landlordIdentity?->last_login_at,
            ],
            'branches' => $this->branchAssignments
                ->where('status', 'active')
                ->sortByDesc('is_primary')
                ->map(fn ($assignment): array => [
                    'assignment_uuid' => $assignment->uuid,
                    'uuid' => $assignment->branch->uuid,
                    'name' => $assignment->branch->name,
                    'is_primary' => (bool) $assignment->is_primary,
                    'valid_from' => $assignment->valid_from,
                    'valid_to' => $assignment->valid_to,
                ])->values(),
            'created_at' => $this->created_at,
        ];
    }
}
