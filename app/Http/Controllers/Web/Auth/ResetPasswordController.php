<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\ResetPasswordRequest;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use App\Services\Tenant\Auth\PasswordResetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly TenantConnectionManager $tenantConnectionManager,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $this->tenantConnectionManager->connectByUuid($request->header('X-Tenant-UUID'));

        $this->passwordResetService->resetPassword($request->validated());

        return ApiResponse::success('Password has been reset successfully. Please sign in again.');
    }
}
