<?php

namespace App\Policies\Tenant\Inventory;

use App\Models\Tenant\Branch;
use App\Models\Tenant\StockAdjustment;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class StockAdjustmentPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.view', $branch);
    }

    public function view(User $user, StockAdjustment $stockAdjustment): bool
    {
        $stockAdjustment->loadMissing('branch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.view', $stockAdjustment->branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.adjust', $branch);
    }

    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        $stockAdjustment->loadMissing('branch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.adjust', $stockAdjustment->branch);
    }

    public function post(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $this->update($user, $stockAdjustment);
    }
}
