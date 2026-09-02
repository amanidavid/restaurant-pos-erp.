<?php

namespace App\Http\Middleware\Tenant;

use App\Modules\Shared\Access\PermissionContextManager;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantConnectionManager $tenantConnectionManager,
        private readonly PermissionContextManager $permissionContextManager,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantConnectionManager->connectByUuid($request->header('X-Tenant-UUID'));

        $this->permissionContextManager->useTenantContext($tenant->uuid);

        return $next($request);
    }
}
