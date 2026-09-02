<?php

namespace App\Services\Tenant\Access;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RoleAccessService
{
    public function create(array $data): Role
    {
        $branchId = null;

        if (! empty($data['branch_uuid'])) {
            $branchId = Branch::query()->where('uuid', $data['branch_uuid'])->value('id');

            if (! $branchId) {
                throw ValidationException::withMessages(['branch_uuid' => ['The selected branch could not be found.']]);
            }
        }

        $duplicate = Role::query()
            ->where('name', $data['name'])
            ->where('guard_name', 'tenant_api')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId), fn ($query) => $query->whereNull('branch_id'))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => ['A role with this name already exists for this scope.']]);
        }

        return Role::query()->create([
            'branch_id' => $branchId,
            'name' => $data['name'],
            'guard_name' => 'tenant_api',
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_protected' => false,
        ]);
    }

    public function update(Role $role, array $data): Role
    {
        $this->assertEditable($role);
        $role->update($data);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $this->assertEditable($role);

        DB::connection('tenant')->transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function syncPermissions(Role $role, array $permissionUuids): Role
    {
        $permissions = Permission::query()->whereIn('uuid', $permissionUuids)->get();

        if ($permissions->count() !== count(array_unique($permissionUuids))) {
            throw ValidationException::withMessages(['permission_uuids' => ['One or more selected permissions could not be found.']]);
        }

        DB::connection('tenant')->transaction(function () use ($role, $permissions): void {
            $role->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh('permissions');
    }

    private function assertEditable(Role $role): void
    {
        if ($role->is_protected) {
            throw ValidationException::withMessages([
                'role' => ['Protected default roles cannot be renamed, changed, or deleted. Their permissions may still be changed.'],
            ]);
        }
    }
}
