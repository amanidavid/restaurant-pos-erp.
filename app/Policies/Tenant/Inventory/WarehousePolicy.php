<?php

namespace App\Policies\Tenant\Inventory;

use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Access\TenantUserAccessService;

class WarehousePolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.view', $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.adjust', $branch);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        $warehouse->loadMissing('branch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.adjust', $warehouse->branch);
    }
}
