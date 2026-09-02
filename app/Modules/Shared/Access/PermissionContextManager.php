<?php

namespace App\Modules\Shared\Access;

use App\Models\Landlord\AdminPermission;
use App\Models\Landlord\AdminRole;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionContextManager
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function useAdminContext(): void
    {
        $this->apply(
            permissionClass: AdminPermission::class,
            roleClass: AdminRole::class,
            tables: [
                'roles' => 'admin_roles',
                'permissions' => 'admin_permissions',
                'model_has_permissions' => 'admin_model_has_permissions',
                'model_has_roles' => 'admin_model_has_roles',
                'role_has_permissions' => 'admin_role_has_permissions',
            ],
            teams: false,
            cacheKey: 'rmis.permission.admin',
        );
    }

    public function useTenantContext(string $tenantUuid): void
    {
        $this->apply(
            permissionClass: Permission::class,
            roleClass: Role::class,
            tables: [
                'roles' => 'roles',
                'permissions' => 'permissions',
                'model_has_permissions' => 'model_has_permissions',
                'model_has_roles' => 'model_has_roles',
                'role_has_permissions' => 'role_has_permissions',
            ],
            teams: true,
            cacheKey: "rmis.permission.tenant.{$tenantUuid}",
        );
    }

    /**
     * @param  array<string, string>  $tables
     */
    private function apply(
        string $permissionClass,
        string $roleClass,
        array $tables,
        bool $teams,
        string $cacheKey,
    ): void {
        $this->registrar->forgetCachedPermissions();
        $this->registrar->setPermissionsTeamId(null);

        config()->set([
            'permission.models.permission' => $permissionClass,
            'permission.models.role' => $roleClass,
            'permission.models.team' => null,
            'permission.table_names' => $tables,
            'permission.teams' => $teams,
            'permission.column_names.team_foreign_key' => 'branch_id',
            'permission.cache.key' => $cacheKey,
        ]);

        $this->registrar
            ->setPermissionClass($permissionClass)
            ->setRoleClass($roleClass)
            ->setTeamClass(null);

        $this->registrar->initializeCache();
    }
}
