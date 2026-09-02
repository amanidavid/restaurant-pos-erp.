<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\User;

class IngredientPolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, Ingredient $ingredient, Branch $branch): bool
    {
        return $ingredient->company_id === $branch->company_id
            && $this->canManageMenu($user, $branch);
    }
}
