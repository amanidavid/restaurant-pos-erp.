<?php

namespace App\Http\Controllers\Web\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Foundation\UpdateBranchConfigurationRequest;
use App\Http\Resources\Web\Foundation\BranchConfigurationResource;
use App\Models\Tenant\Branch;
use App\Services\Tenant\Foundation\BranchConfigurationService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchConfigurationController extends Controller
{
    public function __construct(private readonly BranchConfigurationService $branchConfigurationService) {}

    public function show(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('viewConfiguration', $branch);

        return ApiResponse::resource(
            new BranchConfigurationResource($this->branchConfigurationService->current($branch)),
            ApiMessages::detailsRetrieved('branch_configuration'),
        );
    }

    public function update(UpdateBranchConfigurationRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('updateConfiguration', $branch);

        $configuration = $this->branchConfigurationService->update($branch, $request->validated());

        return ApiResponse::resource(
            new BranchConfigurationResource($configuration),
            ApiMessages::updated('branch_configuration'),
        );
    }
}
