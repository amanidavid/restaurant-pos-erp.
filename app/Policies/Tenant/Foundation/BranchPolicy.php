<?php

namespace App\Policies\Tenant\Foundation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\BranchAccessService;

class BranchPolicy
{
    public function __construct(private readonly BranchAccessService $branchAccess) {}

    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('branches.view') && $this->branchAccess->canAccess($user, $branch);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update') && $this->branchAccess->canAccess($user, $branch);
    }

    public function viewConfiguration(User $user, Branch $branch): bool
    {
        return $user->can('branch_configurations.view') && $this->branchAccess->canAccess($user, $branch);
    }

    public function updateConfiguration(User $user, Branch $branch): bool
    {
        return $user->can('branch_configurations.update') && $this->branchAccess->canAccess($user, $branch);
    }

    public function viewBusinessDate(User $user, Branch $branch): bool
    {
        return $user->can('business_dates.view') && $this->branchAccess->canAccess($user, $branch);
    }

    public function openBusinessDate(User $user, Branch $branch): bool
    {
        return $user->can('business_dates.open') && $this->branchAccess->canAccess($user, $branch);
    }

    public function closeBusinessDate(User $user, Branch $branch): bool
    {
        return $user->can('business_dates.close') && $this->branchAccess->canAccess($user, $branch);
    }
}
