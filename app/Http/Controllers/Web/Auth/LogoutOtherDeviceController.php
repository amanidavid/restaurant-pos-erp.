<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Http\Resources\Web\Auth\AuthSessionResource;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use App\Services\Tenant\Auth\TenantLoginService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LogoutOtherDeviceController extends Controller
{
    public function __construct(
        private readonly TenantConnectionManager $tenantConnectionManager,
        private readonly TenantLoginService $loginService,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $this->tenantConnectionManager->connectByUuid($request->header('X-Tenant-UUID'));

        $session = $this->loginService->login($request->validated(), replaceActiveSession: true);

        return ApiResponse::resource(
            new AuthSessionResource($session),
            'Other device has been logged out. Signed in successfully.',
        );
    }
}
