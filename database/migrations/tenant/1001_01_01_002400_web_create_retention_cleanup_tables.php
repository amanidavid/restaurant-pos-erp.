<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('retention_cleanup_runs')) {
            return;
        }

        $schema->create('retention_cleanup_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('landlord_retention_assignment_uuid')->nullable();
            $table->uuid('landlord_retention_dispatch_uuid')->nullable();
            $table->string('policy_key', 100);
            $table->string('data_area', 40);
            $table->string('cleanup_action', 32);
            $table->unsignedInteger('retention_days');
            $table->timestampTz('cutoff_at');
            $table->string('status', 24)->default('running');
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('rows_scanned')->default(0);
            $table->unsignedInteger('rows_archived')->default(0);
            $table->unsignedInteger('rows_deleted')->default(0);
            $table->unsignedInteger('rows_anonymized')->default(0);
            $table->text('failure_reason')->nullable();
            $table->jsonb('summary')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'started_at'], 'retention_cleanup_runs_status_started_index');
            $table->index(['policy_key', 'started_at'], 'retention_cleanup_runs_policy_started_index');
            $table->index(['data_area', 'started_at'], 'retention_cleanup_runs_area_started_index');
            $table->index(['landlord_retention_assignment_uuid', 'started_at'], 'retention_cleanup_runs_landlord_assignment_index');
            $table->index(['landlord_retention_dispatch_uuid'], 'retention_cleanup_runs_landlord_dispatch_index');
        });

        $schema->create('retention_cleanup_run_tables', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('retention_cleanup_run_id')->constrained('retention_cleanup_runs')->cascadeOnDelete();
            $table->string('target_table', 120);
            $table->string('timestamp_column', 120);
            $table->timestampTz('period_start_at')->nullable();
            $table->timestampTz('period_end_at')->nullable();
            $table->unsignedInteger('rows_scanned')->default(0);
            $table->unsignedInteger('rows_archived')->default(0);
            $table->unsignedInteger('rows_deleted')->default(0);
            $table->unsignedInteger('rows_anonymized')->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['retention_cleanup_run_id', 'target_table'], 'retention_cleanup_run_tables_run_table_index');
            $table->index(['target_table', 'period_end_at'], 'retention_cleanup_run_tables_table_period_index');
        });

        $schema->create('archive_manifests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('retention_cleanup_run_id')->nullable()->constrained('retention_cleanup_runs')->nullOnDelete();
            $table->foreignId('retention_cleanup_run_table_id')->nullable()->constrained('retention_cleanup_run_tables')->nullOnDelete();
            $table->uuid('platform_archive_manifest_uuid')->nullable();
            $table->string('data_area', 40);
            $table->string('target_table', 120);
            $table->string('storage_driver', 32);
            $table->string('storage_path', 700);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestampTz('period_start_at')->nullable();
            $table->timestampTz('period_end_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['storage_driver', 'storage_path'], 'archive_manifests_driver_path_unique');
            $table->index(['data_area', 'created_at'], 'archive_manifests_area_created_index');
            $table->index(['target_table', 'created_at'], 'archive_manifests_table_created_index');
            $table->index(['platform_archive_manifest_uuid'], 'archive_manifests_platform_uuid_index');
            $table->index(['expires_at'], 'archive_manifests_expires_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE retention_cleanup_runs ADD CONSTRAINT retention_cleanup_runs_area_check CHECK (data_area IN ('tenant_audit', 'realtime', 'notification', 'reporting', 'import', 'security', 'device', 'job_history'))");
        $connection->statement("ALTER TABLE retention_cleanup_runs ADD CONSTRAINT retention_cleanup_runs_action_check CHECK (cleanup_action IN ('delete', 'archive_then_delete', 'anonymize', 'summarize_then_delete'))");
        $connection->statement("ALTER TABLE retention_cleanup_runs ADD CONSTRAINT retention_cleanup_runs_status_check CHECK (status IN ('running', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE retention_cleanup_runs ADD CONSTRAINT retention_cleanup_runs_dates_check CHECK (completed_at IS NULL OR completed_at >= started_at)');
        $connection->statement('ALTER TABLE retention_cleanup_runs ADD CONSTRAINT retention_cleanup_runs_values_check CHECK (retention_days BETWEEN 1 AND 3650 AND rows_scanned >= 0 AND rows_archived >= 0 AND rows_deleted >= 0 AND rows_anonymized >= 0)');
        $connection->statement('ALTER TABLE retention_cleanup_run_tables ADD CONSTRAINT retention_cleanup_run_tables_dates_check CHECK ((completed_at IS NULL OR completed_at >= started_at) AND (period_end_at IS NULL OR period_start_at IS NULL OR period_end_at >= period_start_at))');
        $connection->statement('ALTER TABLE retention_cleanup_run_tables ADD CONSTRAINT retention_cleanup_run_tables_counts_check CHECK (rows_scanned >= 0 AND rows_archived >= 0 AND rows_deleted >= 0 AND rows_anonymized >= 0)');
        $connection->statement("ALTER TABLE archive_manifests ADD CONSTRAINT archive_manifests_area_check CHECK (data_area IN ('tenant_audit', 'realtime', 'notification', 'reporting', 'import', 'security', 'device', 'job_history'))");
        $connection->statement("ALTER TABLE archive_manifests ADD CONSTRAINT archive_manifests_driver_check CHECK (storage_driver IN ('local', 's3', 'sftp', 'azure', 'gcs'))");
        $connection->statement('ALTER TABLE archive_manifests ADD CONSTRAINT archive_manifests_values_check CHECK (file_size_bytes > 0 AND row_count >= 0)');
        $connection->statement('ALTER TABLE archive_manifests ADD CONSTRAINT archive_manifests_period_check CHECK (period_end_at IS NULL OR period_start_at IS NULL OR period_end_at >= period_start_at)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('archive_manifests');
        $schema->dropIfExists('retention_cleanup_run_tables');
        $schema->dropIfExists('retention_cleanup_runs');
    }
};
