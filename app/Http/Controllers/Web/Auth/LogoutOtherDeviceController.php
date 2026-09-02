<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Http\Resources\Web\Auth\AuthSessionResource;
use App\Http\Resources\Web\Auth\TenantSelectionResource;
use App\Services\Tenant\Auth\TenantAuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LogoutOtherDeviceController extends Controller
{
    public function __construct(
        private readonly TenantAuthenticationService $authenticationService,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $session = $this->authenticationService->authenticate(
            $request->validated(),
            $request->header('X-Tenant-UUID'),
            replaceActiveSession: true,
        );

        if ($session['requires_tenant_selection']) {
            return ApiResponse::resource(
                new TenantSelectionResource($session),
                'Choose a restaurant workspace to continue.',
            );
        }

        return ApiResponse::resource(
            new AuthSessionResource($session),
            'Other device has been logged out. Signed in successfully.',
        );
    }
}
