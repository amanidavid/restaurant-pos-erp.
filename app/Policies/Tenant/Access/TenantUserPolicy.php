<?php

namespace App\Policies\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\BranchAccessService;
use App\Services\Tenant\Access\TenantUserAccessService;

class TenantUserPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccess,
        private readonly TenantUserAccessService $access
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'users.view', $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'users.invite', $branch);
    }

    public function createForBranch(User $user, Branch $branch): bool
    {
        return $this->create($user, $branch);
    }

    public function createStaff(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'users.invite', $branch)
            && $this->access->canManage($user, 'hr.manage', $branch)
            && $this->access->canManage($user, 'users.roles.update', $branch);
    }

    public function view(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->canManageAssignedUser($user, $tenantUser, $branch, 'users.view');
    }

    public function updateStatus(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $user->uuid !== $tenantUser->landlord_user_uuid
            && $this->canManageAssignedUser($user, $tenantUser, $branch, 'users.status.update');
    }

    public function replaceBranchAccess(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $user->uuid !== $tenantUser->landlord_user_uuid
            && $this->canManageAssignedUser($user, $tenantUser, $branch, 'users.branch_access.update');
    }

    public function manageBranchAccessForBranch(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $user->uuid !== $tenantUser->landlord_user_uuid
            && $this->access->canManage($user, 'users.branch_access.update', $branch);
    }

    public function syncRoles(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->branchAccess->canAccess($user, $branch)
            && $this->canManageAssignedUser($user, $tenantUser, $branch, 'users.roles.update');
    }

    public function syncPermissions(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->branchAccess->canAccess($user, $branch)
            && $this->canManageAssignedUser($user, $tenantUser, $branch, 'users.permissions.update');
    }

    public function viewStaffProfile(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->canManageAssignedUser($user, $tenantUser, $branch, 'hr.manage');
    }

    public function updateStaffProfile(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->canManageAssignedUser($user, $tenantUser, $branch, 'hr.manage');
    }

    public function viewJobHistory(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->canManageAssignedUser($user, $tenantUser, $branch, 'payroll.manage');
    }

    public function createJobHistory(User $user, TenantUser $tenantUser, Branch $branch): bool
    {
        return $this->canManageAssignedUser($user, $tenantUser, $branch, 'payroll.manage');
    }

    private function canManageAssignedUser(User $user, TenantUser $tenantUser, Branch $branch, string $permission): bool
    {
        return $this->access->canManage($user, $permission, $branch)
            && $tenantUser->branchAssignments()
                ->where('branch_id', $branch->id)
                ->where('status', 'active')
                ->exists();
    }
}
