<?php

namespace App\Http\Resources\Web\Access;

use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class TenantUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $identityId = $this->landlordIdentity?->id;

        /* Per-branch roles/permissions live on the Spatie pivots keyed by
         * (model_type, model_id = landlord identity, branch_id). Resolve
         * them in TWO batched queries grouped by branch — no N+1. */
        $rolesByBranch = $identityId
            ? DB::connection('tenant')->table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->where('model_has_roles.model_id', $identityId)
                ->get(['model_has_roles.branch_id', 'roles.uuid', 'roles.name', 'roles.display_name'])
                ->groupBy('branch_id')
            : collect();

        $permissionsByBranch = $identityId
            ? DB::connection('tenant')->table('model_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('model_has_permissions.model_type', User::class)
                ->where('model_has_permissions.model_id', $identityId)
                ->get(['model_has_permissions.branch_id', 'permissions.uuid', 'permissions.name', 'permissions.display_name'])
                ->groupBy('branch_id')
            : collect();

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
                    'roles' => ($rolesByBranch->get($assignment->branch_id) ?? collect())
                        ->map(fn ($role): array => [
                            'uuid' => $role->uuid,
                            'name' => $role->name,
                            'display_name' => $role->display_name,
                        ])->values(),
                    'permissions' => ($permissionsByBranch->get($assignment->branch_id) ?? collect())
                        ->map(fn ($permission): array => [
                            'uuid' => $permission->uuid,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name,
                        ])->values(),
                ])->values(),
            'created_at' => $this->created_at,
        ];
    }
}
