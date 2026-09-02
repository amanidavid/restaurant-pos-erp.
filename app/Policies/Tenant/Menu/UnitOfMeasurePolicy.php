<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\User;

class UnitOfMeasurePolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, UnitOfMeasure $unit, Branch $branch): bool
    {
        return $unit->company_id === $branch->company_id
            && $this->canManageMenu($user, $branch);
    }
}
