<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\RegisterTenantRequest;
use App\Http\Resources\Web\Auth\TenantRegistrationResource;
use App\Services\Landlord\Tenancy\TenantRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterTenantController extends Controller
{
    public function __construct(
        private readonly TenantRegistrationService $registrationService,
    ) {}

    public function __invoke(RegisterTenantRequest $request): JsonResponse
    {
        $result = $this->registrationService->register($request->validated());

        return ApiResponse::resource(
            new TenantRegistrationResource($result),
            'Restaurant workspace registration started successfully.',
            201,
        );
    }
}
