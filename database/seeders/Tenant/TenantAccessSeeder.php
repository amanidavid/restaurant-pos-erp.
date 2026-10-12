<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class TenantAccessSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'tenant';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'permissions')) {
            return;
        }

        $permissions = [
            'dashboard.view' => 'View dashboard',
            'company.view' => 'View company profile',
            'company.update' => 'Update company profile',
            'branches.view' => 'View branches',
            'branches.create' => 'Create branches',
            'branches.update' => 'Update branches',
            'branch_configurations.view' => 'View branch configuration',
            'branch_configurations.update' => 'Update branch configuration',
            'business_dates.view' => 'View current business day',
            'business_dates.open' => 'Open business day',
            'business_dates.close' => 'Close business day',
            'cash_reconciliations.view' => 'View cash reconciliations',
            'cash_reconciliations.open' => 'Open cash reconciliations',
            'cash_reconciliations.count' => 'Enter cash denomination counts',
            'cash_reconciliations.close' => 'Close cash reconciliations',
            'cash_movements.create' => 'Record controlled cash movements',
            'permissions.view' => 'View permission catalogue',
            'roles.view' => 'View roles',
            'roles.create' => 'Create custom roles',
            'roles.update' => 'Update custom roles',
            'roles.delete' => 'Delete custom roles',
            'roles.permissions.update' => 'Update role permissions',
            'users.view' => 'View tenant users',
            'users.invite' => 'Invite tenant users',
            'users.status.update' => 'Update tenant user status',
            'users.branch_access.update' => 'Update user branch access',
            'users.roles.update' => 'Update user branch roles',
            'users.permissions.update' => 'Update user direct permissions',
            'users.department_access.update' => 'Update user department access',
            'orders.create' => 'Create orders',
            'orders.view' => 'View orders',
            'orders.update' => 'Update open order details and items',
            'orders.cancel' => 'Cancel orders',
            'orders.void_item' => 'Void order items',
            'orders.send_to_kitchen' => 'Send orders to kitchen',
            'orders.serve' => 'Mark ready order items as served',
            'orders.close' => 'Close fully paid orders',
            'customers.view' => 'View customers',
            'customers.create' => 'Create customers',
            'customers.update' => 'Update customers',
            'reservations.view' => 'View reservations',
            'reservations.create' => 'Create paid reservations',
            'reservations.update' => 'Update pending reservations',
            'reservations.seat' => 'Seat reservations',
            'reservations.complete' => 'Complete reservations',
            'reservations.cancel' => 'Cancel reservations',
            'reservations.no_show' => 'Mark reservation no show',
            'sales_channels.view' => 'View sales channels',
            'service_types.view' => 'View service types',
            'payment_methods.view' => 'View payment methods',
            'counters.view' => 'View counters',
            'counters.create' => 'Create counters',
            'counters.update' => 'Update counters',
            'dining_areas.view' => 'View dining areas',
            'dining_areas.create' => 'Create dining areas',
            'dining_areas.update' => 'Update dining areas',
            'dining_tables.view' => 'View dining tables',
            'dining_tables.create' => 'Create dining tables',
            'dining_tables.update' => 'Update dining tables',
            'payments.collect' => 'Collect payments',
            'payments.create' => 'Create completed manual payments',
            'receipts.issue' => 'Issue receipts',
            'receipts.view' => 'View receipts',
            'receipts.void' => 'Void issued receipts',
            'receipts.print' => 'Queue receipt print jobs',
            'print_jobs.view' => 'View print job status',
            'refunds.request' => 'Request refunds',
            'refunds.approve' => 'Approve refunds',
            'kitchen.view' => 'View kitchen tickets',
            'kitchen.update' => 'Update kitchen tickets',
            'tables.manage' => 'Manage tables and floor operations',
            'menu.manage' => 'Manage menu items',
            'pricing.manage' => 'Manage pricing',
            'inventory.view' => 'View inventory',
            'inventory.receive' => 'Receive stock',
            'inventory.issue' => 'Issue stock',
            'inventory.count' => 'Count stock',
            'inventory.adjust' => 'Adjust inventory',
            'purchases.manage' => 'Manage purchases',
            'suppliers.manage' => 'Manage suppliers',
            'finance.view' => 'View finance',
            'finance.post_journal' => 'Post journals',
            'expenses.manage' => 'Manage expenses',
            'payroll.manage' => 'Manage payroll',
            'hr.manage' => 'Manage HR records',
            'attendance.manage' => 'Manage attendance and shifts',
            'shifts.view_own' => 'View own shift schedule',
            'reports.sales.view' => 'View sales reports',
            'reports.finance.view' => 'View finance reports',
            'reports.payroll.view' => 'View payroll reports',
            'reports.audit.view' => 'View audit and exception reports',
            'delivery.manage' => 'Manage delivery orders',
            'settings.manage' => 'Manage tenant settings',
            'users.manage' => 'Manage users and access',
            'approvals.decide' => 'Decide approvals',
            'support.create_ticket' => 'Create support tickets',
        ];

        $permissionIds = [];

        foreach ($permissions as $name => $displayName) {
            $permissionIds[$name] = $this->upsertRow(self::CONNECTION, 'permissions', [
                'name' => $name,
                'guard_name' => 'tenant_api',
            ], [
                'display_name' => $displayName,
                'description' => $displayName,
                'is_system' => true,
                'is_protected' => true,
            ]);
        }

        $roles = [
            'business-owner' => [
                'Business Owner',
                array_keys($permissions),
            ],
            'branch-manager' => [
                'Restaurant / Branch Manager',
                [
                    'dashboard.view',
                    'company.view',
                    'branches.view',
                    'branches.update',
                    'branch_configurations.view',
                    'branch_configurations.update',
                    'business_dates.view',
                    'business_dates.open',
                    'business_dates.close',
                    'cash_reconciliations.view',
                    'cash_reconciliations.open',
                    'cash_reconciliations.count',
                    'cash_reconciliations.close',
                    'cash_movements.create',
                    'orders.view',
                    'orders.update',
                    'orders.cancel',
                    'orders.void_item',
                    'orders.send_to_kitchen',
                    'orders.serve',
                    'payments.collect',
                    'refunds.request',
                    'refunds.approve',
                    'kitchen.view',
                    'tables.manage',
                    'menu.manage',
                    'inventory.view',
                    'inventory.count',
                    'inventory.adjust',
                    'purchases.manage',
                    'reports.sales.view',
                    'settings.manage',
                    'users.manage',
                    'approvals.decide',
                    'support.create_ticket',
                ],
            ],
            'operations-manager' => [
                'General / Operations Manager',
                [
                    'dashboard.view',
                    'company.view',
                    'company.update',
                    'branches.view',
                    'branches.create',
                    'branches.update',
                    'branch_configurations.view',
                    'branch_configurations.update',
                    'business_dates.view',
                    'business_dates.open',
                    'business_dates.close',
                    'orders.view',
                    'orders.update',
                    'orders.cancel',
                    'orders.void_item',
                    'orders.send_to_kitchen',
                    'refunds.approve',
                    'tables.manage',
                    'menu.manage',
                    'pricing.manage',
                    'inventory.view',
                    'inventory.adjust',
                    'purchases.manage',
                    'suppliers.manage',
                    'reports.sales.view',
                    'reports.finance.view',
                    'settings.manage',
                    'users.manage',
                    'approvals.decide',
                    'support.create_ticket',
                ],
            ],
            'cashier' => [
                'Cashier',
                [
                    'dashboard.view',
                    'branches.view',
                    'branch_configurations.view',
                    'business_dates.view',
                    'orders.create',
                    'orders.view',
                    'orders.update',
                    'orders.send_to_kitchen',
                    'payments.collect',
                    'refunds.request',
                    'support.create_ticket',
                ],
            ],
            'waiter-server' => [
                'Waiter / Server',
                [
                    'dashboard.view',
                    'branches.view',
                    'orders.create',
                    'orders.view',
                    'orders.update',
                    'orders.send_to_kitchen',
                    'orders.serve',
                    'tables.manage',
                    'support.create_ticket',
                ],
            ],
            'kitchen-staff' => [
                'Kitchen Staff',
                [
                    'dashboard.view',
                    'branches.view',
                    'kitchen.view',
                    'kitchen.update',
                ],
            ],
            'head-chef' => [
                'Head Chef',
                [
                    'dashboard.view',
                    'branches.view',
                    'kitchen.view',
                    'kitchen.update',
                    'inventory.view',
                    'inventory.issue',
                    'support.create_ticket',
                ],
            ],
            'storekeeper' => [
                'Storekeeper',
                [
                    'dashboard.view',
                    'branches.view',
                    'inventory.view',
                    'inventory.receive',
                    'inventory.issue',
                    'inventory.count',
                    'inventory.adjust',
                    'support.create_ticket',
                ],
            ],
            'procurement-officer' => [
                'Procurement Officer',
                [
                    'dashboard.view',
                    'branches.view',
                    'inventory.view',
                    'purchases.manage',
                    'suppliers.manage',
                    'reports.sales.view',
                    'support.create_ticket',
                ],
            ],
            'accountant' => [
                'Accountant',
                [
                    'dashboard.view',
                    'company.view',
                    'branches.view',
                    'finance.view',
                    'finance.post_journal',
                    'expenses.manage',
                    'reports.finance.view',
                    'reports.sales.view',
                    'approvals.decide',
                ],
            ],
            'hr-officer' => [
                'HR Officer',
                [
                    'dashboard.view',
                    'branches.view',
                    'hr.manage',
                    'attendance.manage',
                    'payroll.manage',
                    'reports.payroll.view',
                    'support.create_ticket',
                ],
            ],
            'auditor' => [
                'Auditor',
                [
                    'dashboard.view',
                    'company.view',
                    'branches.view',
                    'orders.view',
                    'inventory.view',
                    'finance.view',
                    'reports.sales.view',
                    'reports.finance.view',
                    'reports.payroll.view',
                    'reports.audit.view',
                    'support.create_ticket',
                ],
            ],
            'delivery-driver' => [
                'Delivery Driver',
                [
                    'dashboard.view',
                    'branches.view',
                    'orders.view',
                    'delivery.manage',
                    'payments.collect',
                    'support.create_ticket',
                ],
            ],
        ];

        foreach ($roles as &$role) {
            $role[1] = array_values(array_unique([...$role[1], 'shifts.view_own']));
        }
        unset($role);

        foreach ($roles as $name => [$displayName, $rolePermissions]) {
            $roleId = $this->upsertRow(self::CONNECTION, 'roles', [
                'branch_id' => null,
                'name' => $name,
                'guard_name' => 'tenant_api',
            ], [
                'display_name' => $displayName,
                'description' => $displayName,
                'is_system' => true,
                'is_protected' => true,
            ]);

            foreach ($rolePermissions as $permissionName) {
                $this->connection(self::CONNECTION)->table('role_has_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permissionName],
                ]);
            }
        }
    }
}
