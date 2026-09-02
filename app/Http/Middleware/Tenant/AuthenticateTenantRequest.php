<?php

namespace App\Http\Middleware\Tenant;

use App\Modules\Shared\Access\PermissionContextManager;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantRequest
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

        try {
            auth()->shouldUse('tenant_api');

            $token = $request->bearerToken();

            if (! is_string($token) || trim($token) === '') {
                throw new \Illuminate\Auth\AuthenticationException;
            }

            // Bind the request token to the tenant JWT guard explicitly. This
            // avoids relying on whichever JWT guard was resolved first.
            $user = auth('tenant_api')
                ->setToken($token)
                ->authenticate();

            if (! $user) {
                throw new \Illuminate\Auth\AuthenticationException;
            }
        } catch (Throwable $exception) {
            report($exception);
            return ApiResponse::unauthorized(message: ApiMessages::AUTHENTICATION_REQUIRED);
        }

        return $next($request);
    }
}
