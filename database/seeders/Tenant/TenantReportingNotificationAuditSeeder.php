<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class TenantReportingNotificationAuditSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'tenant';

    public function run(): void
    {
        $company = $this->connection(self::CONNECTION)->table('companies')->first(['id']);

        if (! $company) {
            $this->command?->warn('TenantReportingNotificationAuditSeeder skipped: no company found.');
            return;
        }

        $companyId = (int) $company->id;
        $this->seedReportDefinitions($companyId);
        $this->seedDashboards($companyId);
        $this->seedApprovalWorkflows($companyId);
        $this->seedNotificationTemplates($companyId);
        $this->seedAuditPolicies($companyId);
        $this->seedSettings();
    }

    private function permissionId(string $name): ?int
    {
        $id = $this->connection(self::CONNECTION)->table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'tenant_api')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function seedReportDefinitions(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'report_definitions')) {
            return;
        }

        foreach ([
            ['daily_sales', 'Daily Sales Report', 'sales', 'reports.sales.view'],
            ['payment_summary', 'Payment Summary', 'payments', 'reports.sales.view'],
            ['stock_valuation', 'Stock Valuation', 'inventory', 'inventory.view'],
            ['profit_and_loss', 'Profit and Loss', 'finance', 'reports.finance.view'],
            ['payroll_summary', 'Payroll Summary', 'payroll', 'reports.payroll.view'],
            ['tax_summary', 'Tax Summary', 'tax', 'reports.finance.view'],
        ] as [$key, $name, $area, $permission]) {
            $this->upsertRow(self::CONNECTION, 'report_definitions', [
                'company_id' => $companyId,
                'report_key' => $key,
            ], [
                'permission_id' => $this->permissionId($permission),
                'name' => $name,
                'report_area' => $area,
                'description' => $name,
                'default_filters' => null,
                'default_columns' => null,
                'default_sort' => null,
                'is_system' => true,
                'status' => 'active',
            ]);
        }
    }

    private function seedDashboards(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'dashboard_definitions')) {
            return;
        }

        foreach ([
            ['Executive Dashboard', 'executive'],
            ['Sales Dashboard', 'sales'],
            ['Kitchen Dashboard', 'kitchen'],
            ['Inventory Dashboard', 'inventory'],
            ['Finance Dashboard', 'finance'],
        ] as [$name, $area]) {
            $dashboardId = $this->upsertRow(self::CONNECTION, 'dashboard_definitions', [
                'company_id' => $companyId,
                'branch_id' => null,
                'name' => $name,
            ], [
                'created_by_tenant_user_id' => null,
                'dashboard_area' => $area,
                'is_system' => true,
                'is_default' => true,
                'status' => 'active',
            ]);

            foreach ($this->widgetsForArea($area) as [$title, $type, $metric, $x, $y]) {
                $this->upsertRow(self::CONNECTION, 'dashboard_widgets', [
                    'dashboard_definition_id' => $dashboardId,
                    'title' => $title,
                ], [
                    'report_definition_id' => null,
                    'widget_type' => $type,
                    'metric_name' => $metric,
                    'configuration' => null,
                    'position_x' => $x,
                    'position_y' => $y,
                    'width' => 4,
                    'height' => 3,
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * @return array<int, array{string, string, string, int, int}>
     */
    private function widgetsForArea(string $area): array
    {
        return match ($area) {
            'sales' => [
                ['Today Sales', 'money', 'today_sales', 0, 0],
                ['Open Orders', 'number', 'open_orders', 4, 0],
            ],
            'kitchen' => [
                ['Open Kitchen Tickets', 'number', 'open_kitchen_tickets', 0, 0],
                ['Ready Tickets', 'number', 'ready_kitchen_tickets', 4, 0],
            ],
            'inventory' => [
                ['Low Stock Items', 'number', 'low_stock_items', 0, 0],
                ['Stock Value', 'money', 'stock_value', 4, 0],
            ],
            'finance' => [
                ['Cash Collected', 'money', 'cash_collected', 0, 0],
                ['Pending Expenses', 'money', 'pending_expenses', 4, 0],
            ],
            default => [
                ['Today Sales', 'money', 'today_sales', 0, 0],
                ['Pending Approvals', 'number', 'pending_approvals', 4, 0],
            ],
        };
    }

    private function seedApprovalWorkflows(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'approval_workflows')) {
            return;
        }

        foreach ([
            ['Refund Approval', 'refund', 'refunds.approve'],
            ['Purchase Approval', 'purchase_order', 'purchases.manage'],
            ['Expense Approval', 'expense_claim', 'expenses.manage'],
            ['Payroll Approval', 'payroll', 'payroll.manage'],
            ['Journal Approval', 'journal_posting', 'finance.post_journal'],
        ] as [$name, $area, $permission]) {
            $workflowId = $this->upsertRow(self::CONNECTION, 'approval_workflows', [
                'company_id' => $companyId,
                'branch_id' => null,
                'name' => $name,
            ], [
                'workflow_area' => $area,
                'minimum_amount' => null,
                'maximum_amount' => null,
                'is_active' => true,
            ]);

            $this->upsertRow(self::CONNECTION, 'approval_workflow_steps', [
                'approval_workflow_id' => $workflowId,
                'step_number' => 1,
            ], [
                'permission_id' => $this->permissionId($permission),
                'name' => 'Manager Approval',
                'required_approval_count' => 1,
                'can_self_approve' => false,
                'is_active' => true,
            ]);
        }
    }

    private function seedNotificationTemplates(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'notification_templates')) {
            return;
        }

        foreach ([
            ['Approval Requested', 'approval.requested', 'in_app', 'Approval required', 'A new approval requires your attention.'],
            ['Refund Approved', 'refund.approved', 'in_app', 'Refund approved', 'A refund has been approved.'],
            ['Low Stock Alert', 'stock.low', 'in_app', 'Low stock alert', 'One or more stock items are below reorder level.'],
            ['Payroll Completed', 'payroll.completed', 'email', 'Payroll completed', 'Payroll processing has completed.'],
        ] as [$name, $event, $channel, $subject, $body]) {
            $this->upsertRow(self::CONNECTION, 'notification_templates', [
                'company_id' => $companyId,
                'branch_id' => null,
                'event_type' => $event,
                'channel' => $channel,
                'locale' => 'en',
            ], [
                'name' => $name,
                'subject_template' => $subject,
                'body_template' => $body,
                'is_active' => true,
            ]);
        }
    }

    private function seedAuditPolicies(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'audit_policies')) {
            return;
        }

        foreach ([
            ['access', 'permission.changed', 'critical', true, true],
            ['configuration', 'setting.changed', 'warning', true, true],
            ['payment', 'payment.voided', 'critical', true, true],
            ['refund', 'refund.approved', 'critical', true, true],
            ['inventory', 'stock.adjusted', 'warning', true, false],
            ['finance', 'journal.posted', 'critical', true, true],
            ['payroll', 'payroll.approved', 'critical', true, true],
            ['tax', 'tax.submitted', 'critical', true, false],
            ['device', 'device.approved', 'warning', true, false],
        ] as [$area, $event, $severity, $reason, $capture]) {
            $this->upsertRow(self::CONNECTION, 'audit_policies', [
                'company_id' => $companyId,
                'audit_area' => $area,
                'event_type' => $event,
            ], [
                'severity' => $severity,
                'is_enabled' => true,
                'requires_reason' => $reason,
                'capture_before_after' => $capture,
                'retention_days' => 730,
            ]);
        }
    }

    private function seedSettings(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'settings')) {
            return;
        }

        foreach ([
            ['tenant', 'timezone', 'Africa/Dar_es_Salaam'],
            ['tenant', 'currency_code', 'TZS'],
            ['receipt', 'auto_print', true],
            ['receipt', 'footer_text', 'Thank you, welcome again.'],
            ['inventory', 'allow_negative_stock', false],
            ['realtime', 'enabled', true],
        ] as [$group, $name, $payload]) {
            $this->connection(self::CONNECTION)->table('settings')->updateOrInsert([
                'group' => $group,
                'name' => $name,
            ], [
                'payload' => json_encode($payload),
                'locked' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }
}
