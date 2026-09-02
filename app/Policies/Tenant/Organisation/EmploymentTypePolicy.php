<?php

namespace App\Policies\Tenant\Organisation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\EmploymentType;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class EmploymentTypePolicy
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

    public function update(User $user, EmploymentType $employmentType, Branch $branch): bool
    {
        return $employmentType->company_id === $branch->company_id
            && $this->access->canManage($user, 'hr.manage', $branch);
    }
}
