<?php

namespace App\Http\Controllers\Web\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Organisation\StoreTenantUserJobHistoryRequest;
use App\Http\Requests\Web\Organisation\UpdateStaffProfileRequest;
use App\Http\Resources\Web\Organisation\StaffProfileResource;
use App\Http\Resources\Web\Organisation\TenantUserJobHistoryResource;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Organisation\StaffProfileService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffProfileController extends Controller
{
    public function __construct(private readonly StaffProfileService $staffProfileService) {}

    public function show(Request $request, TenantUser $tenantUser): JsonResponse
    {
        $this->authorize('viewStaffProfile', [$tenantUser, $this->currentBranch()]);

        return ApiResponse::resource(new StaffProfileResource($this->staffProfileService->profile($tenantUser)), ApiMessages::detailsRetrieved('staff profile'));
    }

    public function update(UpdateStaffProfileRequest $request, TenantUser $tenantUser): JsonResponse
    {
        $this->authorize('updateStaffProfile', [$tenantUser, $this->currentBranch()]);

        return ApiResponse::resource(new StaffProfileResource($this->staffProfileService->update($tenantUser, $request->validated())), ApiMessages::updated('staff profile'));
    }

    public function jobHistory(Request $request, TenantUser $tenantUser): JsonResponse
    {
        $this->authorize('viewJobHistory', [$tenantUser, $this->currentBranch()]);

        $history = $tenantUser->jobHistories()
            ->with(['branch:id,uuid,name', 'department:id,uuid,name', 'jobPosition:id,uuid,name', 'employmentType:id,uuid,name'])
            ->orderByDesc('effective_from')
            ->simplePaginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return ApiResponse::resource(TenantUserJobHistoryResource::collection($history), ApiMessages::listRetrieved('job history'));
    }

    public function storeJobHistory(StoreTenantUserJobHistoryRequest $request, TenantUser $tenantUser): JsonResponse
    {
        $this->authorize('createJobHistory', [$tenantUser, $this->currentBranch()]);

        $history = $this->staffProfileService->addJobHistory($tenantUser, $request->validated());

        return ApiResponse::resource(
            new TenantUserJobHistoryResource($history->load(['branch:id,uuid,name', 'department:id,uuid,name', 'jobPosition:id,uuid,name', 'employmentType:id,uuid,name'])),
            ApiMessages::created('job history'),
            201,
        );
    }

}
