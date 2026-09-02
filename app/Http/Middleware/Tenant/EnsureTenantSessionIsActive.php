<?php

namespace App\Http\Middleware\Tenant;

use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSessionIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = JWTAuth::getToken();
        $user = auth('tenant_api')->user();

        if (! $token || ! $user) {
            return ApiResponse::unauthorized(message: ApiMessages::INVALID_SESSION);
        }

        $payload = JWTAuth::setToken($token)->getPayload();

        if ((int) $payload->get('token_version') !== (int) $user->token_version) {
            return ApiResponse::unauthorized(message: ApiMessages::INVALID_SESSION);
        }

        $tenantUser = $user->tenantUser;

        if (! $tenantUser) {
            return ApiResponse::unauthorized(message: ApiMessages::INVALID_SESSION);
        }

        $session = DB::connection('tenant')
            ->table('tenant_user_device_sessions')
            ->where('tenant_user_id', $tenantUser->id)
            ->where('session_jti_hash', hash('sha256', (string) $token))
            ->where('is_active', true)
            ->whereNull('logged_out_at')
            ->where('expires_at', '>', now())
            ->first(['id', 'branch_id']);

        if (! $session) {
            return ApiResponse::unauthorized(message: ApiMessages::INVALID_SESSION);
        }

        DB::connection('tenant')
            ->table('tenant_user_device_sessions')
            ->where('id', $session->id)
            ->update([
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($session->branch_id);
        app()->instance('currentBranchId', $session->branch_id);

        return $next($request);
    }
}
