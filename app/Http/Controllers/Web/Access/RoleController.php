<?php

namespace App\Http\Controllers\Web\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Access\StoreRoleRequest;
use App\Http\Requests\Web\Access\SyncRolePermissionsRequest;
use App\Http\Requests\Web\Access\UpdateRoleRequest;
use App\Http\Resources\Web\Access\RoleResource;
use App\Http\Resources\Web\Access\PermissionResource;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Services\Tenant\Access\RoleAccessService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleAccessService $roleAccessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Role::class, $this->currentBranch()]);

        $branchIds = $request->user('tenant_api')->tenantUser
            ->branchAssignments()
            ->where('status', 'active')
            ->pluck('branch_id');

        $roles = Role::query()
            ->select('roles.*')
            ->with(['branch:id,uuid,name', 'permissions:id,uuid,name,display_name'])
            ->where(function ($query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->orderByRaw('branch_id NULLS FIRST')
            ->orderBy('display_name')
            ->simplePaginate(min((int) $request->input('per_page', 15), 100));

        return ApiResponse::resource(RoleResource::collection($roles), ApiMessages::listRetrieved('roles'));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', [Role::class, $this->currentBranch()]);

        if ($request->filled('branch_uuid')) {
            $branch = Branch::query()->where('uuid', $request->string('branch_uuid')->toString())->firstOrFail();
            $this->authorize('create', [Role::class, $branch]);
        }

        return ApiResponse::resource(
            new RoleResource($this->roleAccessService->create($request->validated())->load('branch')),
            ApiMessages::created('role'),
            201,
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', [$role, $this->currentBranch()]);

        return ApiResponse::resource(
            new RoleResource($this->roleAccessService->update($role, $request->validated())->load('branch')),
            ApiMessages::updated('role'),
        );
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', [$role, $this->currentBranch()]);

        $this->roleAccessService->delete($role);

        return ApiResponse::success(ApiMessages::deleted('role'));
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $this->authorize('syncPermissions', [$role, $this->currentBranch()]);

        return ApiResponse::resource(
            new RoleResource($this->roleAccessService->syncPermissions($role, $request->validated('permission_uuids'))),
            ApiMessages::updated('role permissions'),
        );
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Permission::class, $this->currentBranch()]);

        $permissions = Permission::query()->orderBy('name')->simplePaginate(min((int) $request->input('per_page', 100), 100));

        return ApiResponse::resource(
            PermissionResource::collection($permissions),
            ApiMessages::listRetrieved('permissions'),
        );
    }

}
