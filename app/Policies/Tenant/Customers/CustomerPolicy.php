<?php

namespace App\Policies\Tenant\Customers;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class CustomerPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool { return $this->access->canManage($user, 'customers.view', $branch); }
    public function create(User $user, Branch $branch): bool { return $this->access->canManage($user, 'customers.create', $branch); }
    public function view(User $user, Customer $customer, Branch $branch): bool { return $customer->company_id === $branch->company_id && $this->viewAny($user, $branch); }
    public function update(User $user, Customer $customer, Branch $branch): bool { return $customer->company_id === $branch->company_id && $this->access->canManage($user, 'customers.update', $branch); }
}
