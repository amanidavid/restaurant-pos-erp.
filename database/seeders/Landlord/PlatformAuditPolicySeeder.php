<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class PlatformAuditPolicySeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'platform_audit_policies')) {
            return;
        }

        foreach ([
            ['tenant', 'tenant.created', 'info', true, true],
            ['tenant', 'tenant.suspended', 'critical', true, true],
            ['database', 'tenant_database.created', 'info', false, false],
            ['database', 'tenant_database.migrated', 'warning', false, false],
            ['subscription', 'subscription.changed', 'warning', true, true],
            ['feature', 'feature.override.changed', 'warning', true, true],
            ['backup', 'backup.completed', 'info', false, false],
            ['restore', 'restore.requested', 'critical', true, true],
            ['restore', 'restore.completed', 'critical', true, true],
            ['security', 'support_access_grant.approved', 'critical', true, true],
        ] as [$area, $event, $severity, $requiresReason, $capture]) {
            $this->upsertRow(self::CONNECTION, 'platform_audit_policies', [
                'audit_area' => $area,
                'event_type' => $event,
            ], [
                'severity' => $severity,
                'is_enabled' => true,
                'retention_days' => 730,
                'requires_reason' => $requiresReason,
                'capture_before_after' => $capture,
            ]);
        }
    }
}
