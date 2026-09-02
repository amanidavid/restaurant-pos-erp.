<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'landlord';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('retention_policy_templates')) {
            return;
        }

        $schema->create('retention_policy_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('policy_key', 100)->unique();
            $table->string('name', 160);
            $table->string('data_area', 40);
            $table->string('target_connection', 24);
            $table->string('target_table', 120);
            $table->string('timestamp_column', 120);
            $table->string('cleanup_action', 32);
            $table->unsignedInteger('retention_days');
            $table->unsignedInteger('batch_size')->default(1000);
            $table->boolean('requires_archive_before_delete')->default(false);
            $table->foreignId('backup_storage_location_id')->nullable()->constrained('backup_storage_locations')->nullOnDelete();
            $table->string('frequency', 24)->default('daily');
            $table->time('scheduled_time')->default('02:00');
            $table->string('timezone_strategy', 32)->default('tenant_timezone');
            $table->unsignedSmallInteger('priority')->default(5);
            $table->string('status', 24)->default('active');
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'frequency', 'scheduled_time'], 'retention_policy_templates_status_frequency_time_index');
            $table->index(['data_area', 'status'], 'retention_policy_templates_area_status_index');
            $table->index(['target_connection', 'target_table'], 'retention_policy_templates_target_index');
        });

        $schema->create('tenant_retention_policy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->foreignId('retention_policy_template_id')->constrained('retention_policy_templates')->restrictOnDelete();
            $table->foreignId('backup_storage_location_id')->nullable()->constrained('backup_storage_locations')->nullOnDelete();
            $table->unsignedInteger('retention_days');
            $table->unsignedInteger('batch_size')->default(1000);
            $table->string('frequency', 24)->default('daily');
            $table->time('scheduled_time')->default('02:00');
            $table->string('timezone', 64);
            $table->timestampTz('next_run_at');
            $table->timestampTz('last_run_at')->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->unsignedSmallInteger('priority')->default(5);
            $table->string('status', 24)->default('active');
            $table->timestampTz('locked_at')->nullable();
            $table->string('locked_by', 120)->nullable();
            $table->text('pause_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'retention_policy_template_id'], 'tenant_retention_assignments_tenant_template_unique');
            $table->index(['status', 'next_run_at', 'priority'], 'tenant_retention_assignments_status_next_priority_index');
            $table->index(['tenant_id', 'status'], 'tenant_retention_assignments_tenant_status_index');
            $table->index(['retention_policy_template_id', 'status'], 'tenant_retention_assignments_template_status_index');
            $table->index(['locked_at'], 'tenant_retention_assignments_locked_at_index');
        });

        $schema->create('tenant_retention_cleanup_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_retention_policy_assignment_id')
                ->constrained('tenant_retention_policy_assignments')
                ->restrictOnDelete();
            $table->uuid('tenant_cleanup_run_uuid')->nullable();
            $table->string('status', 24)->default('queued');
            $table->timestampTz('queued_at');
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('rows_scanned')->default(0);
            $table->unsignedInteger('rows_archived')->default(0);
            $table->unsignedInteger('rows_deleted')->default(0);
            $table->unsignedInteger('rows_anonymized')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'queued_at'], 'tenant_retention_dispatches_tenant_status_queued_index');
            $table->index(['tenant_retention_policy_assignment_id', 'queued_at'], 'tenant_retention_dispatches_assignment_queued_index');
            $table->index(['status', 'queued_at'], 'tenant_retention_dispatches_status_queued_index');
            $table->index(['tenant_cleanup_run_uuid'], 'tenant_retention_dispatches_cleanup_uuid_index');
        });

        $schema->create('platform_archive_manifests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('backup_storage_location_id')->constrained('backup_storage_locations')->restrictOnDelete();
            $table->foreignId('tenant_retention_cleanup_dispatch_id')->nullable()
                ->constrained('tenant_retention_cleanup_dispatches')
                ->nullOnDelete();
            $table->string('data_area', 40);
            $table->string('target_table', 120);
            $table->string('storage_path', 700);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestampTz('period_start_at')->nullable();
            $table->timestampTz('period_end_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['backup_storage_location_id', 'storage_path'], 'platform_archive_manifests_location_path_unique');
            $table->index(['tenant_id', 'data_area', 'created_at'], 'platform_archive_manifests_tenant_area_created_index');
            $table->index(['target_table', 'created_at'], 'platform_archive_manifests_table_created_index');
            $table->index(['expires_at'], 'platform_archive_manifests_expires_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_area_check CHECK (data_area IN ('platform_audit', 'tenant_audit', 'realtime', 'notification', 'reporting', 'import', 'security', 'device', 'backup_history', 'job_history'))");
        $connection->statement("ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_connection_check CHECK (target_connection IN ('landlord', 'tenant'))");
        $connection->statement("ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_action_check CHECK (cleanup_action IN ('delete', 'archive_then_delete', 'anonymize', 'summarize_then_delete'))");
        $connection->statement("ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_frequency_check CHECK (frequency IN ('daily', 'weekly', 'monthly'))");
        $connection->statement("ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_timezone_strategy_check CHECK (timezone_strategy IN ('tenant_timezone', 'fixed_timezone'))");
        $connection->statement("ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_status_check CHECK (status IN ('active', 'inactive', 'retired'))");
        $connection->statement('ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_values_check CHECK (retention_days BETWEEN 1 AND 3650 AND batch_size BETWEEN 100 AND 100000 AND priority BETWEEN 1 AND 9)');
        $connection->statement('ALTER TABLE retention_policy_templates ADD CONSTRAINT retention_policy_templates_archive_check CHECK (requires_archive_before_delete = false OR backup_storage_location_id IS NOT NULL)');
        $connection->statement("ALTER TABLE tenant_retention_policy_assignments ADD CONSTRAINT tenant_retention_assignments_frequency_check CHECK (frequency IN ('daily', 'weekly', 'monthly'))");
        $connection->statement("ALTER TABLE tenant_retention_policy_assignments ADD CONSTRAINT tenant_retention_assignments_status_check CHECK (status IN ('active', 'paused', 'retired', 'failed'))");
        $connection->statement('ALTER TABLE tenant_retention_policy_assignments ADD CONSTRAINT tenant_retention_assignments_values_check CHECK (retention_days BETWEEN 1 AND 3650 AND batch_size BETWEEN 100 AND 100000 AND priority BETWEEN 1 AND 9)');
        $connection->statement("ALTER TABLE tenant_retention_cleanup_dispatches ADD CONSTRAINT tenant_retention_dispatches_status_check CHECK (status IN ('queued', 'dispatched', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE tenant_retention_cleanup_dispatches ADD CONSTRAINT tenant_retention_dispatches_dates_check CHECK ((dispatched_at IS NULL OR dispatched_at >= queued_at) AND (completed_at IS NULL OR dispatched_at IS NULL OR completed_at >= dispatched_at))');
        $connection->statement('ALTER TABLE tenant_retention_cleanup_dispatches ADD CONSTRAINT tenant_retention_dispatches_counts_check CHECK (rows_scanned >= 0 AND rows_archived >= 0 AND rows_deleted >= 0 AND rows_anonymized >= 0)');
        $connection->statement("ALTER TABLE platform_archive_manifests ADD CONSTRAINT platform_archive_manifests_area_check CHECK (data_area IN ('platform_audit', 'tenant_audit', 'realtime', 'notification', 'reporting', 'import', 'security', 'device', 'backup_history', 'job_history'))");
        $connection->statement('ALTER TABLE platform_archive_manifests ADD CONSTRAINT platform_archive_manifests_values_check CHECK (file_size_bytes > 0 AND row_count >= 0)');
        $connection->statement('ALTER TABLE platform_archive_manifests ADD CONSTRAINT platform_archive_manifests_period_check CHECK (period_end_at IS NULL OR period_start_at IS NULL OR period_end_at >= period_start_at)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('platform_archive_manifests');
        $schema->dropIfExists('tenant_retention_cleanup_dispatches');
        $schema->dropIfExists('tenant_retention_policy_assignments');
        $schema->dropIfExists('retention_policy_templates');
    }
};
