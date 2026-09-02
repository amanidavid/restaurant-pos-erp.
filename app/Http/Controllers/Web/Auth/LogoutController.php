<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class LogoutController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $token = JWTAuth::getToken();
        $user = auth('tenant_api')->user();
        $tenantUser = $user?->tenantUser;

        if ($token && $tenantUser) {
            DB::connection('tenant')
                ->table('tenant_user_device_sessions')
                ->where('tenant_user_id', $tenantUser->id)
                ->where('session_jti_hash', hash('sha256', (string) $token))
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'logged_out_at' => now(),
                    'logout_reason' => 'user_logout',
                    'updated_at' => now(),
                ]);

            JWTAuth::setToken($token)->invalidate();
        }

        return ApiResponse::success('Signed out successfully.');
    }
}
