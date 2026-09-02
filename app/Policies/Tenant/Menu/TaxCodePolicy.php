<?php

namespace App\Policies\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\TaxCode;
use App\Models\Tenant\User;

class TaxCodePolicy extends MenuManagePolicy
{
    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->canManageMenu($user, $branch);
    }

    public function update(User $user, TaxCode $taxCode, Branch $branch): bool
    {
        return $taxCode->company_id === $branch->company_id
            && $this->canManageMenu($user, $branch);
    }
}
