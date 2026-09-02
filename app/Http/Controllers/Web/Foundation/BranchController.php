<?php

namespace App\Http\Controllers\Web\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Foundation\IndexBranchRequest;
use App\Http\Requests\Web\Foundation\StoreBranchRequest;
use App\Http\Requests\Web\Foundation\UpdateBranchRequest;
use App\Http\Resources\Web\Foundation\BranchResource;
use App\Models\Tenant\Branch;
use App\Services\Tenant\Foundation\BranchService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branchService) {}

    public function index(IndexBranchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);
        $user = $request->user('tenant_api');

        $tenantUser = $user->tenantUser()->firstOrFail();
        $perPage = min((int) $request->input('per_page', 15), 100);

        $branches = Branch::query()
            ->select('branches.*')
            ->with('activeConfiguration')
            ->join('tenant_user_branches', 'tenant_user_branches.branch_id', '=', 'branches.id')
            ->where('tenant_user_branches.tenant_user_id', $tenantUser->id)
            ->where('tenant_user_branches.status', 'active')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('branches.status', $request->string('status')->toString()),
            )
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(
                    'branches.name_normalized',
                    'like',
                    $request->string('search')->lower()->squish()->toString().'%'
                );
            })
            ->orderBy('branches.name_normalized')
            ->simplePaginate($perPage);

        return ApiResponse::resource(
            BranchResource::collection($branches),
            ApiMessages::listRetrieved('branches'),
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);
        $user = $request->user('tenant_api');

        $branch = $this->branchService->create($request->validated(), $user);

        return ApiResponse::resource(
            new BranchResource($branch),
            ApiMessages::created('branch'),
            201,
        );
    }

    public function show(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return ApiResponse::resource(
            new BranchResource($branch->load('activeConfiguration')),
            ApiMessages::detailsRetrieved('branch'),
        );
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $branch = $this->branchService->update($branch, $request->validated());

        return ApiResponse::resource(
            new BranchResource($branch),
            ApiMessages::updated('branch'),
        );
    }
}
