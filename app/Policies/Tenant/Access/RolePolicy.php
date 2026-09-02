<?php

namespace App\Policies\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class RolePolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'roles.view', $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'roles.create', $branch);
    }

    public function update(User $user, Role $role, Branch $branch): bool
    {
        $targetBranch = $role->branch ?: $branch;

        return $this->access->canManage($user, 'roles.update', $targetBranch);
    }

    public function delete(User $user, Role $role, Branch $branch): bool
    {
        $targetBranch = $role->branch ?: $branch;

        return $this->access->canManage($user, 'roles.delete', $targetBranch);
    }

    public function syncPermissions(User $user, Role $role, Branch $branch): bool
    {
        $targetBranch = $role->branch ?: $branch;

        return $this->access->canManage($user, 'roles.permissions.update', $targetBranch);
    }
}
