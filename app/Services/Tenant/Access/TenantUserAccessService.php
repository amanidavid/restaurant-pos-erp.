<?php

namespace App\Services\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserBranch;
use App\Models\Tenant\User;
use App\Models\Landlord\User as LandlordUser;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class TenantUserAccessService
{
    public function __construct(
        private readonly AccessAuthorizationService $authorizationService,
    ) {}

    /**
     * @return Paginator<int, TenantUser>
     */
    public function paginateForBranch(int $branchId, ?string $status, ?string $search, int $perPage): Paginator
    {
        $identityUuids = null;

        if ($search) {
            $prefix = Str::lower(trim($search));
            $identityUuids = LandlordUser::query()
                ->where(function ($query) use ($prefix): void {
                    $query->whereRaw('LOWER(display_name) LIKE ?', [$prefix.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$prefix.'%'])
                        ->orWhere('phone', 'like', $prefix.'%');
                })
                ->pluck('uuid');
        }

        return TenantUser::query()
            ->select('tenant_users.*')
            ->with([
                'landlordIdentity:id,uuid,first_name,last_name,email,phone,status,last_login_at',
                'branchAssignments.branch:id,uuid,name,status',
            ])
            ->join('tenant_user_branches as visible_assignments', 'visible_assignments.tenant_user_id', '=', 'tenant_users.id')
            ->where('visible_assignments.branch_id', $branchId)
            ->where('visible_assignments.status', 'active')
            ->when($status, fn ($query) => $query->where('tenant_users.status', $status))
            ->when($search, fn ($query) => $query->whereIn('tenant_users.landlord_user_uuid', $identityUuids ?? []))
            ->orderBy('tenant_users.display_name')
            ->orderBy('tenant_users.id')
            ->simplePaginate($perPage);
    }

    public function create(array $data, User $actor): TenantUser
    {
        return DB::connection('landlord')->transaction(function () use ($data, $actor): TenantUser {
            $email = $this->normalizeEmail($data['email'] ?? null);
            $phone = $this->normalizePhone($data['phone'] ?? null);

            $this->assertAvailableLogin($email, $phone);

            $user = LandlordUser::query()->create([
                'uuid' => (string) Str::uuid7(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'display_name' => $this->displayName($data['first_name'], $data['last_name']),
                'email' => $email,
                'phone' => $phone,
                'password' => $data['password'],
                'status' => 'active',
                'password_changed_at' => now(),
            ]);

            $tenantUser = DB::connection('tenant')->transaction(function () use ($user, $actor, $data): TenantUser {
                $tenantUser = TenantUser::query()->create([
                    'landlord_user_uuid' => $user->uuid,
                    'display_name' => $user->display_name,
                    'status' => 'active',
                    'last_synchronized_at' => now(),
                ]);

                $this->replaceBranchAccess($tenantUser, $data['branches'], $actor->tenantUser()->firstOrFail());

                return $tenantUser;
            });

            return $tenantUser->load([
                'landlordIdentity:id,uuid,first_name,last_name,display_name,email,phone,status,last_login_at',
                'branchAssignments.branch:id,uuid,name,status',
            ]);
        });
    }

    public function updateStatus(TenantUser $tenantUser, string $status): TenantUser
    {
        return DB::connection('tenant')->transaction(function () use ($tenantUser, $status): TenantUser {
            $lockedTenantUser = TenantUser::query()
                ->whereKey($tenantUser->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTenantUser->update(['status' => $status]);
            $lockedTenantUser->landlordIdentity()->update(['status' => $status === 'active' ? 'active' : 'suspended']);

            if ($status !== 'active') {
                $lockedTenantUser->landlordIdentity()->increment('token_version');

                DB::connection('tenant')->table('tenant_user_device_sessions')
                    ->where('tenant_user_id', $lockedTenantUser->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'logged_out_at' => now(),
                        'logout_reason' => 'admin_revoked',
                        'updated_at' => now(),
                    ]);
            }

            return $lockedTenantUser->fresh(['landlordIdentity', 'branchAssignments.branch']);
        });
    }

    public function replaceBranchAccess(TenantUser $tenantUser, array $assignments, TenantUser $actor): TenantUser
    {
        return DB::connection('tenant')->transaction(function () use ($tenantUser, $assignments, $actor): TenantUser {
            $branchUuids = collect($assignments)->pluck('branch_uuid')->unique()->values();
            $branches = Branch::query()->whereIn('uuid', $branchUuids)->lockForUpdate()->get(['id', 'uuid']);

            if ($branches->count() !== $branchUuids->count()) {
                throw ValidationException::withMessages(['branches' => ['One or more selected branches could not be found.']]);
            }

            if (collect($assignments)->where('is_primary', true)->count() !== 1) {
                throw ValidationException::withMessages(['branches' => ['Choose exactly one primary branch.']]);
            }

            TenantUserBranch::query()
                ->where('tenant_user_id', $tenantUser->id)
                ->lockForUpdate()
                ->update([
                    'status' => 'revoked',
                    'is_primary' => false,
                    'revoked_by_id' => $actor->id,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $branchesByUuid = $branches->keyBy('uuid');

            foreach ($assignments as $assignment) {
                $branch = $branchesByUuid->get($assignment['branch_uuid']);

                TenantUserBranch::query()->updateOrCreate([
                    'tenant_user_id' => $tenantUser->id,
                    'branch_id' => $branch->id,
                ], [
                    'status' => 'active',
                    'is_primary' => (bool) $assignment['is_primary'],
                    'valid_from' => $assignment['valid_from'] ?? Carbon::today()->toDateString(),
                    'valid_to' => $assignment['valid_to'] ?? null,
                    'assigned_by_id' => $actor->id,
                    'revoked_by_id' => null,
                    'revoked_at' => null,
                ]);
            }

            return $tenantUser->fresh(['user', 'branchAssignments.branch']);
        });
    }

    public function syncRoles(TenantUser $tenantUser, Branch $branch, array $roleUuids): void
    {
        $roles = Role::query()
            ->whereIn('uuid', $roleUuids)
            ->where(function ($query) use ($branch): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            ->get(['id']);

        if ($roles->count() !== count(array_unique($roleUuids))) {
            throw ValidationException::withMessages(['role_uuids' => ['One or more selected roles are not available for this branch.']]);
        }

        DB::connection('tenant')->transaction(function () use ($tenantUser, $branch, $roles): void {
            $identityId = $tenantUser->landlordIdentity()->value('id');

            if (! $identityId) {
                throw ValidationException::withMessages(['tenant_user' => ['The landlord identity mapping is missing.']]);
            }

            DB::connection('tenant')->table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $identityId)
                ->where('branch_id', $branch->id)
                ->delete();

            $rows = $roles->map(fn (Role $role): array => [
                'role_id' => $role->id,
                'branch_id' => $branch->id,
                'model_type' => User::class,
                'model_id' => $identityId,
            ])->all();

            if ($rows !== []) {
                DB::connection('tenant')->table('model_has_roles')->insert($rows);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function syncDirectPermissions(TenantUser $tenantUser, Branch $branch, array $permissionUuids): void
    {
        $permissions = Permission::query()->whereIn('uuid', $permissionUuids)->get(['id']);

        if ($permissions->count() !== count(array_unique($permissionUuids))) {
            throw ValidationException::withMessages(['permission_uuids' => ['One or more selected permissions could not be found.']]);
        }

        DB::connection('tenant')->transaction(function () use ($tenantUser, $branch, $permissions): void {
            $identityId = $tenantUser->landlordIdentity()->value('id');

            if (! $identityId) {
                throw ValidationException::withMessages(['tenant_user' => ['The landlord identity mapping is missing.']]);
            }

            DB::connection('tenant')->table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', $identityId)
                ->where('branch_id', $branch->id)
                ->delete();

            $rows = $permissions->map(fn (Permission $permission): array => [
                'permission_id' => $permission->id,
                'branch_id' => $branch->id,
                'model_type' => User::class,
                'model_id' => $identityId,
            ])->all();

            if ($rows !== []) {
                DB::connection('tenant')->table('model_has_permissions')->insert($rows);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function canManage(User $actor, string $permission, Branch $branch): bool
    {
        return $this->authorizationService->allows($actor, $permission, $branch->id);
    }

    private function assertAvailableLogin(?string $email, ?string $phone): void
    {
        if ($email && LandlordUser::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => ['This email address is already in use in this restaurant workspace.']]);
        }

        if ($phone && LandlordUser::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => ['This phone number is already in use in this restaurant workspace.']]);
        }
    }

    private function normalizeEmail(?string $email): ?string
    {
        return $email ? Str::lower(trim($email)) : null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '0') => '+255'.substr($digits, 1),
            str_starts_with($digits, '255') => '+'.$digits,
            default => '+255'.$digits,
        };
    }

    private function displayName(string $firstName, string $lastName): string
    {
        return Str::title(Str::lower(trim($firstName.' '.$lastName)));
    }
}
