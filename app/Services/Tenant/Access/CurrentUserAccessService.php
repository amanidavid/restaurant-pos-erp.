<?php

namespace App\Services\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CurrentUserAccessService
{
    /**
     * @return array<string, mixed>
     */
    public function profile(User $user): array
    {
        $tenantUser = $user->tenantUser()
            ->with(['homeBranch:id,uuid,name,status'])
            ->firstOrFail();

        $company = Company::query()
            ->select(['id', 'uuid', 'legal_name', 'trading_name', 'country_code', 'timezone', 'locale', 'base_currency_code', 'status'])
            ->orderBy('id')
            ->first();

        $branches = $this->branches($tenantUser);

        return [
            'user' => $user,
            'tenant_user' => $tenantUser,
            'company' => $company,
            'branches' => $branches,
            'active_branch' => $this->activeBranch($branches),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function permissions(User $user): array
    {
        $profile = $this->profile($user);

        return array_merge($profile, [
            'branch_permissions' => $this->branchPermissions($user, $profile['branches']),
        ]);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(TenantUser $tenantUser): Collection
    {
        return Branch::query()
            ->select([
                'branches.id',
                'branches.uuid',
                'branches.company_id',
                'branches.name',
                'branches.timezone',
                'branches.currency_code',
                'branches.status',
                'tenant_user_branches.is_primary as pivot_is_primary',
            ])
            ->join('tenant_user_branches', 'tenant_user_branches.branch_id', '=', 'branches.id')
            ->where('tenant_user_branches.tenant_user_id', $tenantUser->id)
            ->where('tenant_user_branches.status', 'active')
            ->where('branches.status', 'active')
            ->orderByDesc('tenant_user_branches.is_primary')
            ->orderBy('branches.name')
            ->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     */
    private function activeBranch(Collection $branches): ?Branch
    {
        return $branches->first();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, array<string, mixed>>
     */
    private function branchPermissions(User $user, Collection $branches): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }

        $branchIds = $branches->pluck('id')->all();

        $roles = DB::connection('tenant')
            ->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.branch_id', $branchIds)
            ->select([
                'model_has_roles.branch_id',
                'roles.id',
                'roles.uuid',
                'roles.name',
                'roles.display_name',
                'roles.is_protected',
            ])
            ->get()
            ->groupBy('branch_id');

        $rolePermissions = DB::connection('tenant')
            ->table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.branch_id', $branchIds)
            ->select([
                'model_has_roles.branch_id',
                'permissions.name',
            ])
            ->get()
            ->groupBy('branch_id');

        $directPermissions = DB::connection('tenant')
            ->table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', User::class)
            ->where('model_has_permissions.model_id', $user->id)
            ->whereIn('model_has_permissions.branch_id', $branchIds)
            ->select([
                'model_has_permissions.branch_id',
                'permissions.name',
            ])
            ->get()
            ->groupBy('branch_id');

        return $branches->map(function (Branch $branch) use ($roles, $rolePermissions, $directPermissions): array {
            $branchRoleRows = $roles->get($branch->id, collect());
            $permissionNames = $rolePermissions->get($branch->id, collect())
                ->merge($directPermissions->get($branch->id, collect()))
                ->pluck('name')
                ->unique()
                ->sort()
                ->values();

            return [
                'branch' => [
                    'uuid' => $branch->uuid,
                    'name' => $branch->name,
                    'is_primary' => (bool) $branch->pivot_is_primary,
                ],
                'roles' => $branchRoleRows->map(fn ($role): array => [
                    'uuid' => $role->uuid,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'is_protected' => (bool) $role->is_protected,
                ])->values(),
                'permissions' => $permissionNames,
            ];
        })->values();
    }
}
