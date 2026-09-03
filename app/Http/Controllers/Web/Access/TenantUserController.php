<?php

namespace App\Http\Controllers\Web\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Access\IndexTenantUserRequest;
use App\Http\Requests\Web\Access\ReplaceTenantUserBranchAccessRequest;
use App\Http\Requests\Web\Access\StoreTenantUserRequest;
use App\Http\Requests\Web\Access\SyncBranchPermissionsRequest;
use App\Http\Requests\Web\Access\SyncBranchRolesRequest;
use App\Http\Requests\Web\Access\UpdateTenantUserStatusRequest;
use App\Http\Resources\Web\Access\TenantUserResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Access\TenantUserAccessService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantUserController extends Controller
{
    public function __construct(
        private readonly TenantUserAccessService $tenantUserAccessService,
    ) {}

    public function index(IndexTenantUserRequest $request): JsonResponse
    {
        $branchId = $this->currentBranchId();
        $this->authorize('viewAny', [TenantUser::class, $this->currentBranch()]);

        $users = $this->tenantUserAccessService->paginateForBranch(
            $branchId,
            $request->string('status')->toString() ?: null,
            $request->filled('search') ? $request->string('search')->toString() : null,
            min((int) $request->input('per_page', 15), 100),
        );

        return ApiResponse::resource(TenantUserResource::collection($users), ApiMessages::listRetrieved('tenant users'));
    }

    public function store(StoreTenantUserRequest $request): JsonResponse
    {
        $actor = $request->user('tenant_api');
        $this->authorize('create', [TenantUser::class, $this->currentBranch()]);

        foreach ($request->validated('branches') as $assignment) {
            $branch = Branch::query()->where('uuid', $assignment['branch_uuid'])->firstOrFail();
            $this->authorize('createForBranch', [TenantUser::class, $branch]);
        }

        $tenantUser = $this->tenantUserAccessService->create($request->validated(), $actor);

        return ApiResponse::resource(new TenantUserResource($tenantUser), ApiMessages::created('tenant user'), 201);
    }

    public function show(Request $request, TenantUser $tenantUser): JsonResponse
    {
        $this->authorize('view', [$tenantUser, $this->currentBranch()]);

        return ApiResponse::resource(
            new TenantUserResource($tenantUser->load([
                'landlordIdentity:id,uuid,first_name,last_name,email,phone,status,last_login_at',
                'branchAssignments.branch:id,uuid,name,status',
            ])),
            ApiMessages::detailsRetrieved('tenant user'),
        );
    }

    public function updateStatus(UpdateTenantUserStatusRequest $request, TenantUser $tenantUser): JsonResponse
    {
        $this->authorize('updateStatus', [$tenantUser, $this->currentBranch()]);

        return ApiResponse::resource(
            new TenantUserResource($this->tenantUserAccessService->updateStatus($tenantUser, $request->string('status')->toString())),
            ApiMessages::updated('tenant user status'),
        );
    }

    public function replaceBranchAccess(ReplaceTenantUserBranchAccessRequest $request, TenantUser $tenantUser): JsonResponse
    {
        $actor = $request->user('tenant_api');
        $this->authorize('replaceBranchAccess', [$tenantUser, $this->currentBranch()]);

        $branchUuids = $tenantUser->branchAssignments()
            ->where('status', 'active')
            ->with('branch:id,uuid')
            ->get()
            ->pluck('branch.uuid')
            ->merge(collect($request->validated('branches'))->pluck('branch_uuid'))
            ->unique();

        foreach ($branchUuids as $branchUuid) {
            $branch = Branch::query()->where('uuid', $branchUuid)->firstOrFail();
            $this->authorize('manageBranchAccessForBranch', [$tenantUser, $branch]);
        }

        return ApiResponse::resource(
            new TenantUserResource($this->tenantUserAccessService->replaceBranchAccess(
                $tenantUser,
                $request->validated('branches'),
                $actor->tenantUser()->firstOrFail(),
            )),
            ApiMessages::updated('tenant user branch access'),
        );
    }

    public function syncRoles(SyncBranchRolesRequest $request, TenantUser $tenantUser, Branch $branch): JsonResponse
    {
        $this->authorize('syncRoles', [$tenantUser, $branch]);

        $this->assertTargetHasBranch($tenantUser, $branch);
        $this->tenantUserAccessService->syncRoles($tenantUser, $branch, $request->validated('role_uuids'));

        return ApiResponse::success(ApiMessages::updated('tenant user roles'));
    }

    public function syncPermissions(SyncBranchPermissionsRequest $request, TenantUser $tenantUser, Branch $branch): JsonResponse
    {
        $this->authorize('syncPermissions', [$tenantUser, $branch]);

        $this->assertTargetHasBranch($tenantUser, $branch);
        $this->tenantUserAccessService->syncDirectPermissions($tenantUser, $branch, $request->validated('permission_uuids'));

        return ApiResponse::success(ApiMessages::updated('tenant user direct permissions'));
    }

    private function assertTargetHasBranch(TenantUser $tenantUser, Branch $branch): void
    {
        $hasBranchAccess = $tenantUser->branchAssignments()
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasBranchAccess) {
            throw ValidationException::withMessages([
                'branch' => ['The selected user does not have access to this branch.'],
            ]);
        }
    }

}
