<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\VerifyPasswordResetOtpRequest;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use App\Services\Tenant\Auth\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VerifyPasswordResetOtpController extends Controller
{
    public function __construct(
        private readonly TenantConnectionManager $tenantConnectionManager,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function __invoke(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        $this->tenantConnectionManager->connectByUuid($request->header('X-Tenant-UUID'));

        return ApiResponse::success(
            'Password reset code verified successfully.',
            $this->passwordResetService->verifyOtp($request->validated()),
        );
    }
}
