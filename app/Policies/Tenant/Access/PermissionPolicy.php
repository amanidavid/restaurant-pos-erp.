<?php

namespace App\Policies\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Permission;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class PermissionPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'permissions.view', $branch);
    }
}
