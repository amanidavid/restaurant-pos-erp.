<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

abstract class MenuManagePolicy
{
    public function __construct(protected readonly TenantUserAccessService $access) {}

    protected function canManageMenu(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'menu.manage', $branch);
    }
}
