<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class BackupRetentionSupportSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        $this->seedBackupLocation();
        $this->seedRetentionPolicies();
        $this->seedSupportCategories();
    }

    private function seedBackupLocation(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'backup_storage_locations')) {
            return;
        }

        $this->upsertRow(self::CONNECTION, 'backup_storage_locations', [
            'name' => 'Local Default Backups',
        ], [
            'created_by_platform_user_id' => null,
            'driver' => 'local',
            'region' => null,
            'bucket' => null,
            'base_path' => 'storage/app/backups',
            'credential_secret_reference' => null,
            'is_default' => true,
            'supports_encryption' => true,
            'supports_versioning' => false,
            'status' => 'active',
            'last_health_check_at' => null,
        ]);
    }

    private function seedRetentionPolicies(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'retention_policy_templates')) {
            return;
        }

        $locationId = $this->connection(self::CONNECTION)->table('backup_storage_locations')
            ->where('is_default', true)
            ->where('status', 'active')
            ->value('id');

        $policies = [
            ['realtime_outbox_published_14d', 'Published realtime outbox cleanup', 'realtime', 'tenant', 'realtime_event_outbox', 'published_at', 'delete', 14, false],
            ['tenant_audit_archive_730d', 'Tenant audit archive', 'tenant_audit', 'tenant', 'audit_events', 'occurred_at', 'archive_then_delete', 730, true],
            ['notification_events_90d', 'Notification event cleanup', 'notification', 'tenant', 'notification_delivery_events', 'event_at', 'delete', 90, false],
            ['import_errors_90d', 'Import error cleanup', 'import', 'tenant', 'data_import_errors', 'created_at', 'delete', 90, false],
            ['platform_audit_archive_730d', 'Platform audit archive', 'platform_audit', 'landlord', 'platform_audit_events', 'occurred_at', 'archive_then_delete', 730, true],
        ];

        foreach ($policies as [$key, $name, $area, $connection, $table, $timestamp, $action, $days, $archive]) {
            $this->upsertRow(self::CONNECTION, 'retention_policy_templates', [
                'policy_key' => $key,
            ], [
                'created_by_platform_user_id' => null,
                'name' => $name,
                'data_area' => $area,
                'target_connection' => $connection,
                'target_table' => $table,
                'timestamp_column' => $timestamp,
                'cleanup_action' => $action,
                'retention_days' => $days,
                'batch_size' => 1000,
                'requires_archive_before_delete' => $archive,
                'backup_storage_location_id' => $archive ? $locationId : null,
                'frequency' => 'daily',
                'scheduled_time' => '02:00:00',
                'timezone_strategy' => 'tenant_timezone',
                'priority' => 5,
                'status' => 'active',
                'description' => $name,
            ]);
        }
    }

    private function seedSupportCategories(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'support_ticket_categories')) {
            return;
        }

        foreach ([
            ['Technical Issue', 'technical', 5, false],
            ['Payment Issue', 'payment', 3, true],
            ['Realtime/WebSocket Issue', 'realtime', 3, false],
            ['Backup or Restore', 'backup', 2, true],
            ['Billing and Subscription', 'billing', 5, false],
            ['Performance Issue', 'performance', 3, false],
            ['Training Request', 'training', 7, false],
        ] as [$name, $area, $priority, $requiresGrant]) {
            $this->upsertRow(self::CONNECTION, 'support_ticket_categories', [
                'name' => $name,
            ], [
                'support_area' => $area,
                'default_assignee_platform_user_id' => null,
                'default_priority' => $priority,
                'requires_support_access_grant' => $requiresGrant,
                'status' => 'active',
            ]);
        }
    }
}
