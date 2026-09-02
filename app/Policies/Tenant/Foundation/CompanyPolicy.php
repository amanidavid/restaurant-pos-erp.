<?php

namespace App\Policies\Tenant\Foundation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class CompanyPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function view(User $user, Company $company, Branch $branch): bool
    {
        return $company->id === $branch->company_id
            && $this->access->canManage($user, 'company.view', $branch);
    }

    public function update(User $user, Company $company, Branch $branch): bool
    {
        return $company->id === $branch->company_id
            && $this->access->canManage($user, 'company.update', $branch);
    }
}
