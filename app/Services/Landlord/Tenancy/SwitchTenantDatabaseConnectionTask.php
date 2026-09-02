<?php

namespace App\Services\Landlord\Tenancy;

use App\Models\Landlord\Tenant;
use RuntimeException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchTenantDatabaseConnectionTask implements SwitchTenantTask
{
    public function __construct(
        private readonly TenantConnectionManager $tenantConnectionManager,
    ) {}

    public function makeCurrent(IsTenant $tenant): void
    {
        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('The configured multitenancy tenant model must be the landlord Tenant model.');
        }

        $this->tenantConnectionManager->configure($tenant);
    }

    public function forgetCurrent(): void
    {
        $this->tenantConnectionManager->forget();
    }
}
