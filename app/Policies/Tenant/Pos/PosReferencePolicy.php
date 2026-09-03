<?php

namespace App\Policies\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class PosReferencePolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'orders.view', $branch);
    }
}
