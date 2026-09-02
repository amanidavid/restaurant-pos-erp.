<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\User;

class MenuCategoryPolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, MenuCategory $menuCategory, Branch $branch): bool
    {
        return $menuCategory->company_id === $branch->company_id
            && $this->canManageMenu($user, $branch);
    }
}
