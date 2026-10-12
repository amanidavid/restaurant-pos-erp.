<?php

namespace App\Policies\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class PosReferencePolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewSalesChannels(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'sales_channels.view', $branch);
    }

    public function viewServiceTypes(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'service_types.view', $branch);
    }

    public function viewPaymentMethods(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'payment_methods.view', $branch);
    }

    public function viewCounters(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'counters.view', $branch);
    }

    public function createCounters(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'counters.create', $branch);
    }

    public function updateCounter(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'counters.update', $branch);
    }

    public function viewDiningAreas(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'dining_areas.view', $branch);
    }

    public function createFloorPlan(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'dining_areas.create', $branch)
            && $this->access->canManage($user, 'dining_tables.create', $branch);
    }

    public function updateDiningArea(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'dining_areas.update', $branch);
    }

    public function viewDiningTables(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'dining_tables.view', $branch);
    }

    public function createDiningTable(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'dining_tables.create', $branch);
    }

    public function updateDiningTable(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'dining_tables.update', $branch);
    }
}
