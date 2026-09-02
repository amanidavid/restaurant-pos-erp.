<?php

namespace App\Http\Controllers\Web\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\Me\CurrentUserPermissionsResource;
use App\Services\Tenant\Access\CurrentUserAccessService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CurrentUserPermissionsController extends Controller
{
    public function __construct(
        private readonly CurrentUserAccessService $accessService,
    ) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::resource(
            new CurrentUserPermissionsResource($this->accessService->permissions(auth('tenant_api')->user())),
            ApiMessages::detailsRetrieved('current_user_permissions'),
        );
    }
}
