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

        if ($schema->hasTable('report_definitions')) {
            return;
        }

        $schema->create('report_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->nullOnDelete();
            $table->string('report_key', 100);
            $table->string('name', 160);
            $table->string('report_area', 40);
            $table->text('description')->nullable();
            $table->jsonb('default_filters')->nullable();
            $table->jsonb('default_columns')->nullable();
            $table->jsonb('default_sort')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'report_key'], 'report_definitions_company_key_unique');
            $table->unique(['company_id', 'name'], 'report_definitions_company_name_unique');
            $table->index(['company_id', 'report_area', 'status'], 'report_definitions_company_area_status_index');
            $table->index(['permission_id', 'status'], 'report_definitions_permission_status_index');
        });

        $schema->create('saved_report_views', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('name', 140);
            $table->jsonb('filters')->nullable();
            $table->jsonb('columns')->nullable();
            $table->jsonb('sort')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['report_definition_id', 'tenant_user_id', 'name'], 'saved_report_views_report_user_name_unique');
            $table->index(['tenant_user_id', 'status'], 'saved_report_views_user_status_index');
            $table->index(['branch_id', 'status'], 'saved_report_views_branch_status_index');
        });

        $schema->create('report_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_definition_id')->constrained('report_definitions')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('business_document_id')->nullable()->constrained('business_documents')->nullOnDelete();
            $table->uuid('landlord_schedule_assignment_uuid')->nullable();
            $table->uuid('landlord_schedule_dispatch_uuid')->nullable();
            $table->string('run_type', 24)->default('manual');
            $table->string('export_format', 24)->nullable();
            $table->string('status', 24)->default('queued');
            $table->jsonb('filters')->nullable();
            $table->jsonb('columns')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['report_definition_id', 'status', 'queued_at'], 'report_runs_report_status_queued_index');
            $table->index(['branch_id', 'status', 'queued_at'], 'report_runs_branch_status_queued_index');
            $table->index(['requested_by_tenant_user_id', 'queued_at'], 'report_runs_requested_by_queued_index');
            $table->index(['business_document_id'], 'report_runs_document_index');
            $table->index(['landlord_schedule_assignment_uuid', 'queued_at'], 'report_runs_landlord_assignment_queued_index');
            $table->index(['landlord_schedule_dispatch_uuid'], 'report_runs_landlord_dispatch_index');
        });

        $schema->create('report_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_run_id')->constrained('report_runs')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('notification_message_id')->nullable()->constrained('notification_messages')->nullOnDelete();
            $table->uuid('landlord_schedule_recipient_uuid')->nullable();
            $table->string('recipient_name_snapshot', 180)->nullable();
            $table->string('recipient_email_snapshot', 254)->nullable();
            $table->string('recipient_phone_snapshot', 20)->nullable();
            $table->string('channel', 24);
            $table->string('status', 24)->default('queued');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['report_run_id', 'status'], 'report_delivery_logs_run_status_index');
            $table->index(['tenant_user_id', 'status'], 'report_delivery_logs_user_status_index');
            $table->index(['notification_message_id'], 'report_delivery_logs_message_index');
            $table->index(['landlord_schedule_recipient_uuid'], 'report_delivery_logs_landlord_recipient_index');
        });

        $schema->create('dashboard_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('created_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('dashboard_area', 40);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'name'], 'dashboard_definitions_scope_name_unique');
            $table->index(['company_id', 'dashboard_area', 'status'], 'dashboard_definitions_company_area_status_index');
            $table->index(['branch_id', 'status'], 'dashboard_definitions_branch_status_index');
        });

        $schema->create('dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dashboard_definition_id')->constrained('dashboard_definitions')->cascadeOnDelete();
            $table->foreignId('report_definition_id')->nullable()->constrained('report_definitions')->nullOnDelete();
            $table->string('title', 160);
            $table->string('widget_type', 32);
            $table->string('metric_name', 80)->nullable();
            $table->jsonb('configuration')->nullable();
            $table->unsignedSmallInteger('position_x')->default(0);
            $table->unsignedSmallInteger('position_y')->default(0);
            $table->unsignedSmallInteger('width')->default(4);
            $table->unsignedSmallInteger('height')->default(3);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['dashboard_definition_id', 'status'], 'dashboard_widgets_dashboard_status_index');
            $table->index(['report_definition_id', 'status'], 'dashboard_widgets_report_status_index');
            $table->index(['metric_name', 'status'], 'dashboard_widgets_metric_status_index');
        });

        $schema->create('kpi_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('metric_area', 40);
            $table->string('metric_name', 80);
            $table->string('granularity', 24);
            $table->timestampTz('period_start_at');
            $table->timestampTz('period_end_at');
            $table->decimal('numeric_value', 20, 4)->default(0);
            $table->decimal('money_value', 20, 2)->nullable();
            $table->jsonb('breakdown')->nullable();
            $table->timestampTz('computed_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'metric_name', 'granularity', 'period_start_at'], 'kpi_snapshots_scope_metric_period_unique');
            $table->index(['company_id', 'metric_area', 'granularity', 'period_start_at'], 'kpi_snapshots_company_area_period_index');
            $table->index(['branch_id', 'metric_area', 'granularity', 'period_start_at'], 'kpi_snapshots_branch_area_period_index');
            $table->index(['metric_name', 'granularity', 'period_start_at'], 'kpi_snapshots_metric_period_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE report_definitions ADD CONSTRAINT report_definitions_area_check CHECK (report_area IN ('sales', 'payments', 'inventory', 'procurement', 'finance', 'tax', 'payroll', 'staff', 'customers', 'operations', 'audit'))");
        $connection->statement("ALTER TABLE report_definitions ADD CONSTRAINT report_definitions_status_check CHECK (status IN ('active', 'inactive', 'archived'))");
        $connection->statement("ALTER TABLE saved_report_views ADD CONSTRAINT saved_report_views_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("CREATE UNIQUE INDEX saved_report_views_one_default_unique ON saved_report_views (report_definition_id, tenant_user_id) WHERE is_default = true AND status = 'active'");
        $connection->statement("ALTER TABLE report_runs ADD CONSTRAINT report_runs_type_check CHECK (run_type IN ('manual', 'scheduled', 'system'))");
        $connection->statement("ALTER TABLE report_runs ADD CONSTRAINT report_runs_format_check CHECK (export_format IS NULL OR export_format IN ('pdf', 'xlsx', 'csv', 'html', 'json'))");
        $connection->statement("ALTER TABLE report_runs ADD CONSTRAINT report_runs_status_check CHECK (status IN ('queued', 'running', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE report_runs ADD CONSTRAINT report_runs_dates_check CHECK ((started_at IS NULL OR started_at >= queued_at) AND (completed_at IS NULL OR started_at IS NULL OR completed_at >= started_at))');
        $connection->statement('ALTER TABLE report_runs ADD CONSTRAINT report_runs_row_count_check CHECK (row_count IS NULL OR row_count >= 0)');
        $connection->statement("ALTER TABLE report_delivery_logs ADD CONSTRAINT report_delivery_logs_channel_check CHECK (channel IN ('email', 'sms', 'whatsapp', 'push', 'in_app'))");
        $connection->statement("ALTER TABLE report_delivery_logs ADD CONSTRAINT report_delivery_logs_status_check CHECK (status IN ('queued', 'sent', 'failed'))");
        $connection->statement('ALTER TABLE report_delivery_logs ADD CONSTRAINT report_delivery_logs_dates_check CHECK (failed_at IS NULL OR sent_at IS NULL)');
        $connection->statement("ALTER TABLE dashboard_definitions ADD CONSTRAINT dashboard_definitions_area_check CHECK (dashboard_area IN ('executive', 'sales', 'cashier', 'kitchen', 'inventory', 'procurement', 'finance', 'payroll', 'tax', 'operations'))");
        $connection->statement("ALTER TABLE dashboard_definitions ADD CONSTRAINT dashboard_definitions_status_check CHECK (status IN ('active', 'inactive', 'archived'))");
        $connection->statement("CREATE UNIQUE INDEX dashboard_definitions_one_default_company_unique ON dashboard_definitions (company_id, dashboard_area) WHERE branch_id IS NULL AND is_default = true AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX dashboard_definitions_one_default_branch_unique ON dashboard_definitions (branch_id, dashboard_area) WHERE branch_id IS NOT NULL AND is_default = true AND status = 'active'");
        $connection->statement("ALTER TABLE dashboard_widgets ADD CONSTRAINT dashboard_widgets_type_check CHECK (widget_type IN ('number', 'money', 'table', 'line_chart', 'bar_chart', 'pie_chart', 'status_list'))");
        $connection->statement("ALTER TABLE dashboard_widgets ADD CONSTRAINT dashboard_widgets_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE dashboard_widgets ADD CONSTRAINT dashboard_widgets_size_check CHECK (width > 0 AND height > 0)');
        $connection->statement("ALTER TABLE kpi_snapshots ADD CONSTRAINT kpi_snapshots_area_check CHECK (metric_area IN ('sales', 'payments', 'inventory', 'procurement', 'finance', 'tax', 'payroll', 'staff', 'customers', 'operations'))");
        $connection->statement("ALTER TABLE kpi_snapshots ADD CONSTRAINT kpi_snapshots_granularity_check CHECK (granularity IN ('hourly', 'daily', 'weekly', 'monthly'))");
        $connection->statement('ALTER TABLE kpi_snapshots ADD CONSTRAINT kpi_snapshots_dates_check CHECK (period_end_at > period_start_at AND computed_at >= period_end_at)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS dashboard_definitions_one_default_branch_unique');
        $connection->statement('DROP INDEX IF EXISTS dashboard_definitions_one_default_company_unique');
        $connection->statement('DROP INDEX IF EXISTS saved_report_views_one_default_unique');

        $schema->dropIfExists('kpi_snapshots');
        $schema->dropIfExists('dashboard_widgets');
        $schema->dropIfExists('dashboard_definitions');
        $schema->dropIfExists('report_delivery_logs');
        $schema->dropIfExists('report_runs');
        $schema->dropIfExists('saved_report_views');
        $schema->dropIfExists('report_definitions');
    }
};
