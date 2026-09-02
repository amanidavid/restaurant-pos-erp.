<?php

namespace App\Http\Controllers\Web\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\Me\CurrentUserResource;
use App\Services\Tenant\Access\CurrentUserAccessService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CurrentUserController extends Controller
{
    public function __construct(
        private readonly CurrentUserAccessService $accessService,
    ) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::resource(
            new CurrentUserResource($this->accessService->profile(auth('tenant_api')->user())),
            ApiMessages::detailsRetrieved('current_user'),
        );
    }
}
