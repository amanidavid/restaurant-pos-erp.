<?php

namespace App\Policies\Tenant\Organisation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\JobPosition;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class JobPositionPolicy
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

    public function update(User $user, JobPosition $jobPosition, Branch $branch): bool
    {
        return $jobPosition->company_id === $branch->company_id
            && $this->access->canManage($user, 'hr.manage', $branch);
    }
}
