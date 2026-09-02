<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\ForgotPasswordRequest;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use App\Services\Tenant\Auth\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly TenantConnectionManager $tenantConnectionManager,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $this->tenantConnectionManager->connectByUuid($request->header('X-Tenant-UUID'));

        return ApiResponse::success(
            'Password reset code has been sent successfully.',
            $this->passwordResetService->requestOtp($request->validated()),
        );
    }
}
