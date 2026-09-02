<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\User;

class KitchenSectionPolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, KitchenSection $kitchenSection, Branch $branch): bool
    {
        return $kitchenSection->branch_id === $branch->id
            && $this->canManageMenu($user, $branch);
    }
}
