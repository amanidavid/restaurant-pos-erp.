<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class PlatformAccessSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'admin_permissions')) {
            return;
        }

        $permissions = [
            'platform.tenants.view' => 'View tenants',
            'platform.tenants.manage' => 'Manage tenants',
            'platform.users.manage' => 'Manage platform users',
            'platform.subscriptions.manage' => 'Manage subscriptions',
            'platform.features.manage' => 'Manage features',
            'platform.backups.manage' => 'Manage backups and restores',
            'platform.support.manage' => 'Manage support tickets',
            'platform.health.view' => 'View platform health monitoring',
            'platform.settings.manage' => 'Manage platform settings',
            'platform.reports.manage' => 'Manage report schedules',
            'platform.audit.view' => 'View platform audit',
        ];

        $permissionIds = [];

        foreach ($permissions as $name => $displayName) {
            $permissionIds[$name] = $this->upsertRow(self::CONNECTION, 'admin_permissions', [
                'name' => $name,
                'guard_name' => 'admin',
            ], [
                'display_name' => $displayName,
                'description' => $displayName,
                'is_system' => true,
            ]);
        }

        $roles = [
            'platform-owner' => [
                'display_name' => 'Platform Owner',
                'permissions' => array_keys($permissions),
            ],
            'super-admin' => [
                'display_name' => 'Super Admin',
                'permissions' => array_keys($permissions),
            ],
            'support-agent' => [
                'display_name' => 'Support Agent',
                'permissions' => [
                    'platform.tenants.view',
                    'platform.support.manage',
                    'platform.health.view',
                    'platform.audit.view',
                ],
            ],
            'billing-admin' => [
                'display_name' => 'Billing Admin',
                'permissions' => [
                    'platform.tenants.view',
                    'platform.subscriptions.manage',
                    'platform.features.manage',
                ],
            ],
        ];

        foreach ($roles as $name => $role) {
            $roleId = $this->upsertRow(self::CONNECTION, 'admin_roles', [
                'name' => $name,
                'guard_name' => 'admin',
            ], [
                'display_name' => $role['display_name'],
                'description' => $role['display_name'],
                'is_system' => true,
                'is_protected' => true,
            ]);

            foreach ($role['permissions'] as $permissionName) {
                $this->connection(self::CONNECTION)->table('admin_role_has_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permissionName],
                ]);
            }
        }
    }
}
