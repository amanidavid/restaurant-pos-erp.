<?php

namespace App\Http\Controllers\Web\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Foundation\CloseBusinessDateRequest;
use App\Http\Requests\Web\Foundation\OpenBusinessDateRequest;
use App\Http\Resources\Web\Foundation\BusinessDateResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDate;
use App\Services\Tenant\Foundation\BusinessDateService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BusinessDateController extends Controller
{
    public function __construct(private readonly BusinessDateService $businessDateService) {}

    public function current(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('viewBusinessDate', $branch);

        $businessDay = $this->businessDateService->current($branch);

        return ApiResponse::success(
            ApiMessages::detailsRetrieved('business_day'),
            $businessDay ? (new BusinessDateResource($businessDay->load('branch')))->resolve() : null,
        );
    }

    public function open(OpenBusinessDateRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('openBusinessDate', $branch);
        $user = $request->user('tenant_api');

        $businessDay = $this->businessDateService->open($branch, $user->tenantUser()->firstOrFail(), $request->validated());

        return ApiResponse::resource(new BusinessDateResource($businessDay), 'Business day opened successfully.', 201);
    }

    public function close(CloseBusinessDateRequest $request, Branch $branch, BusinessDate $businessDay): JsonResponse
    {
        $this->authorize('closeBusinessDate', $branch);
        $user = $request->user('tenant_api');

        try {
            $businessDay = $this->businessDateService->close($branch, $businessDay, $user->tenantUser()->firstOrFail(), $request->validated());
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            return ApiResponse::error(
                message: implode(' ', $errors['business_day'] ?? ['This business day cannot be closed yet.']),
                errors: $errors,
                status: 422,
                code: 'BUSINESS_DAY_CLOSE_BLOCKED',
            );
        }

        return ApiResponse::resource(new BusinessDateResource($businessDay), 'Business day closed successfully.');
    }
}
