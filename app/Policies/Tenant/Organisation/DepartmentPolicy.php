<?php

namespace App\Policies\Tenant\Organisation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class DepartmentPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'hr.manage', $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->viewAny($user, $branch);
    }

    public function update(User $user, Department $department, Branch $branch): bool
    {
        return $this->access->canManage($user, 'hr.manage', $branch)
            && ($department->branch_id === null || $department->branch_id === $branch->id);
    }
}
