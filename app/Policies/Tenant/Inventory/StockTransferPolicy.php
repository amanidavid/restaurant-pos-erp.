<?php

namespace App\Policies\Tenant\Inventory;

use App\Models\Tenant\Branch;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class StockTransferPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.view', $branch);
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        $stockTransfer->loadMissing('fromBranch:id,uuid,name', 'toBranch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.view', $stockTransfer->fromBranch)
            || $this->access->canManage($user, 'inventory.view', $stockTransfer->toBranch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'inventory.issue', $branch);
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        $stockTransfer->loadMissing('fromBranch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.issue', $stockTransfer->fromBranch);
    }

    public function dispatch(User $user, StockTransfer $stockTransfer): bool
    {
        return $this->update($user, $stockTransfer);
    }

    public function receive(User $user, StockTransfer $stockTransfer): bool
    {
        $stockTransfer->loadMissing('toBranch:id,uuid,name');

        return $this->access->canManage($user, 'inventory.receive', $stockTransfer->toBranch);
    }
}
