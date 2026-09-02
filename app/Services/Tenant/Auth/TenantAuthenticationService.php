<?php

namespace App\Services\Tenant\Auth;

use App\Services\Landlord\Auth\TenantLoginContextService;
use App\Services\Landlord\Tenancy\TenantConnectionManager;

class TenantAuthenticationService
{
    public function __construct(
        private readonly TenantLoginService $loginService,
        private readonly TenantLoginContextService $loginContextService,
        private readonly TenantConnectionManager $tenantConnectionManager,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function authenticate(
        array $data,
        ?string $tenantHeader = null,
        bool $replaceActiveSession = false,
    ): array {
        $user = $this->loginService->authenticate($data);
        $tenants = $this->loginContextService->activeTenants($user);
        $requestedTenantUuid = $data['tenant_uuid'] ?? $tenantHeader;
        $tenant = $this->loginContextService->resolve($tenants, $requestedTenantUuid);

        if (! $tenant) {
            return [
                'requires_tenant_selection' => true,
                'tenants' => $tenants,
            ];
        }

        $this->tenantConnectionManager->connectByUuid($tenant->uuid);
        $session = $this->loginService->login(
            $data,
            replaceActiveSession: $replaceActiveSession,
            authenticatedUser: $user,
        );

        return array_merge($session, [
            'requires_tenant_selection' => false,
            'tenant' => $tenant,
            'tenants' => $tenants,
        ]);
    }
}
