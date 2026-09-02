<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\ModifierGroup;
use App\Models\Tenant\User;

class ModifierGroupPolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, ModifierGroup $modifierGroup, Branch $branch): bool
    {
        return $modifierGroup->company_id === $branch->company_id
            && $this->canManageMenu($user, $branch);
    }
}
