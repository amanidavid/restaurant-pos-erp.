<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\User;

class MenuItemPolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, MenuItem $menuItem, Branch $branch): bool
    {
        return $menuItem->company_id === $branch->company_id
            && $this->canManageMenu($user, $branch);
    }

    public function viewRecipe(User $user, MenuItem $menuItem, Branch $branch): bool
    {
        return $this->update($user, $menuItem, $branch);
    }

    public function syncRecipe(User $user, MenuItem $menuItem, Branch $branch): bool
    {
        return $this->update($user, $menuItem, $branch);
    }

    public function syncModifiers(User $user, MenuItem $menuItem, Branch $branch): bool
    {
        return $this->update($user, $menuItem, $branch);
    }

    public function syncCombo(User $user, MenuItem $menuItem, Branch $branch): bool
    {
        return $this->update($user, $menuItem, $branch);
    }

    public function uploadImage(User $user, MenuItem $menuItem, Branch $branch): bool
    {
        return $this->update($user, $menuItem, $branch);
    }

    public function import(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function export(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }
}
