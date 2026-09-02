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

        if ($schema->hasTable('backup_storage_locations')) {
            return;
        }

        $schema->create('backup_storage_locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('name', 140);
            $table->string('driver', 32);
            $table->string('region', 80)->nullable();
            $table->string('bucket', 180)->nullable();
            $table->string('base_path', 500);
            $table->string('credential_secret_reference', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_encryption')->default(true);
            $table->boolean('supports_versioning')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampTz('last_health_check_at')->nullable();
            $table->timestampsTz();

            $table->unique(['name'], 'backup_storage_locations_name_unique');
            $table->index(['driver', 'status'], 'backup_storage_locations_driver_status_index');
            $table->index(['is_default', 'status'], 'backup_storage_locations_default_status_index');
        });

        $schema->create('tenant_backup_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->foreignId('backup_storage_location_id')->constrained('backup_storage_locations')->restrictOnDelete();
            $table->foreignId('created_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('name', 140);
            $table->string('backup_scope', 24);
            $table->string('frequency', 24);
            $table->time('scheduled_time');
            $table->string('timezone', 64);
            $table->timestampTz('next_run_at');
            $table->timestampTz('last_run_at')->nullable();
            $table->boolean('run_before_tenant_migration')->default(true);
            $table->boolean('include_media')->default(true);
            $table->boolean('encrypt_backup')->default(true);
            $table->boolean('compress_backup')->default(true);
            $table->unsignedSmallInteger('retention_daily')->default(7);
            $table->unsignedSmallInteger('retention_weekly')->default(4);
            $table->unsignedSmallInteger('retention_monthly')->default(12);
            $table->unsignedSmallInteger('priority')->default(5);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'name'], 'tenant_backup_policies_tenant_name_unique');
            $table->index(['status', 'next_run_at', 'priority'], 'tenant_backup_policies_status_next_priority_index');
            $table->index(['tenant_id', 'status'], 'tenant_backup_policies_tenant_status_index');
            $table->index(['backup_storage_location_id', 'status'], 'tenant_backup_policies_location_status_index');
        });

        $schema->create('tenant_backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->foreignId('tenant_backup_policy_id')->nullable()->constrained('tenant_backup_policies')->nullOnDelete();
            $table->foreignId('backup_storage_location_id')->constrained('backup_storage_locations')->restrictOnDelete();
            $table->foreignId('requested_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('run_type', 24);
            $table->string('backup_scope', 24);
            $table->string('status', 24)->default('queued');
            $table->string('database_name_snapshot', 63)->nullable();
            $table->string('schema_version_snapshot', 100)->nullable();
            $table->string('postgres_version_snapshot', 80)->nullable();
            $table->string('backup_tool', 40)->nullable();
            $table->string('backup_format', 24)->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_compressed')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->default(0);
            $table->string('manifest_path', 700)->nullable();
            $table->string('manifest_checksum_sha256', 64)->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'queued_at'], 'tenant_backup_runs_tenant_status_queued_index');
            $table->index(['tenant_backup_policy_id', 'queued_at'], 'tenant_backup_runs_policy_queued_index');
            $table->index(['backup_storage_location_id', 'status'], 'tenant_backup_runs_location_status_index');
            $table->index(['status', 'queued_at'], 'tenant_backup_runs_status_queued_index');
            $table->index(['expires_at'], 'tenant_backup_runs_expires_index');
        });

        $schema->create('tenant_backup_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_backup_run_id')->constrained('tenant_backup_runs')->cascadeOnDelete();
            $table->foreignId('backup_storage_location_id')->constrained('backup_storage_locations')->restrictOnDelete();
            $table->string('artifact_type', 32);
            $table->string('storage_path', 700);
            $table->string('file_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_compressed')->default(false);
            $table->timestampsTz();

            $table->unique(['backup_storage_location_id', 'storage_path'], 'tenant_backup_artifacts_location_path_unique');
            $table->index(['tenant_backup_run_id', 'artifact_type'], 'tenant_backup_artifacts_run_type_index');
            $table->index(['checksum_sha256'], 'tenant_backup_artifacts_checksum_index');
        });

        $schema->create('tenant_restore_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_backup_run_id')->constrained('tenant_backup_runs')->restrictOnDelete();
            $table->foreignId('requested_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('approved_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('restore_mode', 32);
            $table->string('target_database_name', 63)->nullable();
            $table->text('reason');
            $table->string('status', 24)->default('pending_approval');
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'requested_at'], 'tenant_restore_requests_tenant_status_requested_index');
            $table->index(['tenant_backup_run_id', 'status'], 'tenant_restore_requests_backup_status_index');
            $table->index(['status', 'requested_at'], 'tenant_restore_requests_status_requested_index');
        });

        $schema->create('tenant_restore_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_restore_request_id')->constrained('tenant_restore_requests')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('source_backup_run_id')->constrained('tenant_backup_runs')->restrictOnDelete();
            $table->foreignId('target_tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->string('status', 24)->default('queued');
            $table->string('current_step', 100)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('queued_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('validation_summary')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'queued_at'], 'tenant_restore_runs_tenant_status_queued_index');
            $table->index(['tenant_restore_request_id', 'status'], 'tenant_restore_runs_request_status_index');
            $table->index(['source_backup_run_id'], 'tenant_restore_runs_source_backup_index');
        });

        $schema->create('tenant_database_maintenance_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->foreignId('performed_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('tenant_backup_run_id')->nullable()->constrained('tenant_backup_runs')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('status', 24)->default('started');
            $table->string('database_name_snapshot', 63)->nullable();
            $table->string('from_schema_version', 100)->nullable();
            $table->string('to_schema_version', 100)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'event_type', 'started_at'], 'tenant_db_maintenance_tenant_type_started_index');
            $table->index(['tenant_database_id', 'started_at'], 'tenant_db_maintenance_database_started_index');
            $table->index(['status', 'started_at'], 'tenant_db_maintenance_status_started_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE backup_storage_locations ADD CONSTRAINT backup_storage_locations_driver_check CHECK (driver IN ('local', 's3', 'sftp', 'azure', 'gcs'))");
        $connection->statement("ALTER TABLE backup_storage_locations ADD CONSTRAINT backup_storage_locations_status_check CHECK (status IN ('active', 'inactive', 'failed'))");
        $connection->statement("CREATE UNIQUE INDEX backup_storage_locations_one_default_unique ON backup_storage_locations (is_default) WHERE is_default = true AND status = 'active'");
        $connection->statement("ALTER TABLE tenant_backup_policies ADD CONSTRAINT tenant_backup_policies_scope_check CHECK (backup_scope IN ('database', 'media', 'full'))");
        $connection->statement("ALTER TABLE tenant_backup_policies ADD CONSTRAINT tenant_backup_policies_frequency_check CHECK (frequency IN ('hourly', 'daily', 'weekly', 'monthly', 'before_migration'))");
        $connection->statement("ALTER TABLE tenant_backup_policies ADD CONSTRAINT tenant_backup_policies_status_check CHECK (status IN ('active', 'paused', 'retired'))");
        $connection->statement('ALTER TABLE tenant_backup_policies ADD CONSTRAINT tenant_backup_policies_retention_check CHECK (retention_daily >= 0 AND retention_weekly >= 0 AND retention_monthly >= 0)');
        $connection->statement('ALTER TABLE tenant_backup_policies ADD CONSTRAINT tenant_backup_policies_priority_check CHECK (priority BETWEEN 1 AND 9)');
        $connection->statement("ALTER TABLE tenant_backup_runs ADD CONSTRAINT tenant_backup_runs_type_check CHECK (run_type IN ('scheduled', 'manual', 'before_migration', 'before_restore'))");
        $connection->statement("ALTER TABLE tenant_backup_runs ADD CONSTRAINT tenant_backup_runs_scope_check CHECK (backup_scope IN ('database', 'media', 'full'))");
        $connection->statement("ALTER TABLE tenant_backup_runs ADD CONSTRAINT tenant_backup_runs_status_check CHECK (status IN ('queued', 'running', 'completed', 'failed', 'cancelled', 'expired'))");
        $connection->statement("ALTER TABLE tenant_backup_runs ADD CONSTRAINT tenant_backup_runs_format_check CHECK (backup_format IS NULL OR backup_format IN ('plain_sql', 'custom', 'directory', 'tar', 'zip'))");
        $connection->statement('ALTER TABLE tenant_backup_runs ADD CONSTRAINT tenant_backup_runs_dates_check CHECK ((started_at IS NULL OR started_at >= queued_at) AND (completed_at IS NULL OR started_at IS NULL OR completed_at >= started_at) AND (expires_at IS NULL OR completed_at IS NULL OR expires_at >= completed_at))');
        $connection->statement('ALTER TABLE tenant_backup_runs ADD CONSTRAINT tenant_backup_runs_size_attempts_check CHECK (total_size_bytes >= 0 AND attempts >= 0)');
        $connection->statement("ALTER TABLE tenant_backup_artifacts ADD CONSTRAINT tenant_backup_artifacts_type_check CHECK (artifact_type IN ('database_dump', 'media_archive', 'manifest', 'log', 'checksum'))");
        $connection->statement('ALTER TABLE tenant_backup_artifacts ADD CONSTRAINT tenant_backup_artifacts_size_check CHECK (size_bytes > 0)');
        $connection->statement("ALTER TABLE tenant_restore_requests ADD CONSTRAINT tenant_restore_requests_mode_check CHECK (restore_mode IN ('restore_to_new_database', 'replace_existing_database', 'export_only'))");
        $connection->statement("ALTER TABLE tenant_restore_requests ADD CONSTRAINT tenant_restore_requests_status_check CHECK (status IN ('pending_approval', 'approved', 'rejected', 'queued', 'running', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE tenant_restore_requests ADD CONSTRAINT tenant_restore_requests_decision_check CHECK ((approved_at IS NULL AND rejected_at IS NULL) OR (approved_at IS NOT NULL AND rejected_at IS NULL) OR (approved_at IS NULL AND rejected_at IS NOT NULL))');
        $connection->statement("ALTER TABLE tenant_restore_runs ADD CONSTRAINT tenant_restore_runs_status_check CHECK (status IN ('queued', 'running', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE tenant_restore_runs ADD CONSTRAINT tenant_restore_runs_dates_check CHECK ((started_at IS NULL OR started_at >= queued_at) AND (completed_at IS NULL OR started_at IS NULL OR completed_at >= started_at) AND (validated_at IS NULL OR completed_at IS NULL OR validated_at >= completed_at))');
        $connection->statement("ALTER TABLE tenant_database_maintenance_events ADD CONSTRAINT tenant_database_maintenance_type_check CHECK (event_type IN ('backup', 'restore', 'migration', 'schema_check', 'vacuum', 'analyze', 'reindex', 'archive_cleanup', 'health_check'))");
        $connection->statement("ALTER TABLE tenant_database_maintenance_events ADD CONSTRAINT tenant_database_maintenance_status_check CHECK (status IN ('started', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE tenant_database_maintenance_events ADD CONSTRAINT tenant_database_maintenance_dates_check CHECK (completed_at IS NULL OR completed_at >= started_at)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS backup_storage_locations_one_default_unique');

        $schema->dropIfExists('tenant_database_maintenance_events');
        $schema->dropIfExists('tenant_restore_runs');
        $schema->dropIfExists('tenant_restore_requests');
        $schema->dropIfExists('tenant_backup_artifacts');
        $schema->dropIfExists('tenant_backup_runs');
        $schema->dropIfExists('tenant_backup_policies');
        $schema->dropIfExists('backup_storage_locations');
    }
};
