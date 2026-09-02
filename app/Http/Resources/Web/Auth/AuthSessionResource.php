<?php

namespace App\Http\Resources\Web\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $tenantUser = $this->resource['tenant_user'];

        return [
            // The workspace UUID is different from the user and branch UUIDs.
            'tenant_uuid' => $request->header('X-Tenant-UUID'),
            'access_token' => $this->resource['access_token'],
            'token_type' => 'bearer',
            'expires_in' => $this->resource['expires_in'],
            'user' => [
                'uuid' => $user->uuid,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
            'tenant_user' => [
                'uuid' => $tenantUser->uuid,
                'display_name' => $tenantUser->display_name,
                'status' => $tenantUser->status,
            ],
            'branches' => $this->resource['branches']->map(fn ($branch): array => [
                'uuid' => $branch->uuid,
                'name' => $branch->name,
                'status' => $branch->status,
                'is_primary' => (bool) $branch->pivot_is_primary,
            ])->values(),
            'active_branch_uuid' => $this->resource['active_branch']?->uuid,
        ];
    }
}
