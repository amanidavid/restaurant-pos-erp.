<?php

namespace App\Services\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\TenantUserBranch;
use App\Models\Tenant\User;

class BranchAccessService
{
    public function canAccess(User $user, Branch $branch): bool
    {
        $tenantUserId = $user->tenantUser()->value('id');

        if (! $tenantUserId) {
            return false;
        }

        return TenantUserBranch::query()
            ->where('tenant_user_id', $tenantUserId)
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->exists();
    }
}
