<?php

namespace App\Policies\Tenant\Inventory;

use App\Models\Tenant\Branch;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class StockCountPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.view', $branch);
    }

    public function view(User $user, StockCount $stockCount): bool
    {
        $stockCount->loadMissing('branch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.view', $stockCount->branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.count', $branch);
    }

    public function update(User $user, StockCount $stockCount): bool
    {
        $stockCount->loadMissing('branch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.count', $stockCount->branch);
    }

    public function post(User $user, StockCount $stockCount): bool
    {
        return $this->update($user, $stockCount);
    }
}
