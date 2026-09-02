<?php

namespace App\Services\Landlord\Auth;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TenantLoginContextService
{
    /**
     * @return Collection<int, Tenant>
     */
    public function activeTenants(User $user): Collection
    {
        return Tenant::query()
            ->select([
                'tenants.id',
                'tenants.uuid',
                'tenants.legal_name',
                'tenants.trading_name',
                'tenants.status',
                'tenant_accounts.is_owner',
            ])
            ->join('tenant_accounts', 'tenant_accounts.tenant_id', '=', 'tenants.id')
            ->where('tenant_accounts.user_id', $user->id)
            ->where('tenant_accounts.status', 'active')
            ->whereIn('tenants.status', ['trial', 'active'])
            ->orderByDesc('tenant_accounts.is_owner')
            ->orderBy('tenants.trading_name')
            ->orderBy('tenants.id')
            ->get();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    public function resolve(Collection $tenants, ?string $requestedTenantUuid): ?Tenant
    {
        if ($tenants->isEmpty()) {
            throw new AccessDeniedHttpException('This account has no active restaurant workspace.');
        }

        if (is_string($requestedTenantUuid) && trim($requestedTenantUuid) !== '') {
            return $tenants->firstWhere('uuid', trim($requestedTenantUuid))
                ?? throw new AccessDeniedHttpException('You do not have access to the selected restaurant workspace.');
        }

        return $tenants->count() === 1 ? $tenants->first() : null;
    }
}
