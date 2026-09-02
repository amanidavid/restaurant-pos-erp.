<?php

namespace App\Services\Tenant\Access;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class AccessAuthorizationService
{
    public function allows(User $user, string $permission, int $branchId): bool
    {
        $rolePermissionExists = DB::connection('tenant')
            ->table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.branch_id', $branchId)
            ->where('permissions.name', $permission)
            ->exists();

        if ($rolePermissionExists) {
            return true;
        }

        return DB::connection('tenant')
            ->table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', User::class)
            ->where('model_has_permissions.model_id', $user->id)
            ->where('model_has_permissions.branch_id', $branchId)
            ->where('permissions.name', $permission)
            ->exists();
    }
}
