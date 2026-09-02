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

        if ($schema->hasTable('report_schedule_templates')) {
            return;
        }

        $schema->create('report_schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('feature_id')->nullable()->constrained('features')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('created_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('report_key', 100);
            $table->string('report_area', 40);
            $table->string('frequency', 24);
            $table->time('scheduled_time');
            $table->string('timezone_strategy', 32)->default('tenant_timezone');
            $table->string('timezone', 64)->nullable();
            $table->string('export_format', 24)->default('pdf');
            $table->jsonb('default_filters')->nullable();
            $table->jsonb('delivery_options')->nullable();
            $table->boolean('allow_tenant_time_override')->default(false);
            $table->boolean('auto_assign_to_new_tenants')->default(true);
            $table->unsignedSmallInteger('priority')->default(5);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['report_key', 'frequency', 'scheduled_time'], 'report_schedule_templates_key_frequency_time_unique');
            $table->index(['status', 'frequency', 'scheduled_time'], 'report_schedule_templates_status_frequency_time_index');
            $table->index(['feature_id', 'status'], 'report_schedule_templates_feature_status_index');
            $table->index(['package_id', 'status'], 'report_schedule_templates_package_status_index');
        });

        $schema->create('tenant_report_schedule_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->foreignId('report_schedule_template_id')->constrained('report_schedule_templates')->restrictOnDelete();
            $table->uuid('tenant_branch_uuid')->nullable();
            $table->uuid('tenant_report_definition_uuid')->nullable();
            $table->string('frequency', 24);
            $table->time('scheduled_time');
            $table->string('timezone', 64);
            $table->string('export_format', 24)->default('pdf');
            $table->jsonb('filters')->nullable();
            $table->jsonb('delivery_options')->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->unsignedSmallInteger('priority')->default(5);
            $table->timestampTz('next_run_at');
            $table->timestampTz('last_dispatched_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->string('locked_by', 120)->nullable();
            $table->unsignedSmallInteger('lock_version')->default(1);
            $table->string('status', 24)->default('active');
            $table->text('pause_reason')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'next_run_at', 'priority'], 'tenant_report_assignments_status_next_priority_index');
            $table->index(['tenant_id', 'status'], 'tenant_report_assignments_tenant_status_index');
            $table->index(['report_schedule_template_id', 'status'], 'tenant_report_assignments_template_status_index');
            $table->index(['locked_at'], 'tenant_report_assignments_locked_at_index');
        });

        $schema->create('tenant_report_schedule_recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_report_schedule_assignment_id')
                ->constrained('tenant_report_schedule_assignments')
                ->cascadeOnDelete();
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->uuid('tenant_user_uuid')->nullable();
            $table->string('recipient_type', 32);
            $table->string('channel', 24);
            $table->string('name_snapshot', 180)->nullable();
            $table->string('email_snapshot', 254)->nullable();
            $table->string('phone_snapshot', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['tenant_report_schedule_assignment_id', 'is_active'], 'tenant_report_recipients_assignment_active_index');
            $table->index(['platform_user_id', 'is_active'], 'tenant_report_recipients_platform_user_active_index');
            $table->index(['tenant_user_uuid', 'is_active'], 'tenant_report_recipients_tenant_user_active_index');
        });

        $schema->create('tenant_report_schedule_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_report_schedule_assignment_id')
                ->constrained('tenant_report_schedule_assignments')
                ->restrictOnDelete();
            $table->uuid('tenant_report_run_uuid')->nullable();
            $table->string('status', 24)->default('queued');
            $table->timestampTz('queued_at');
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_report_schedule_assignment_id', 'queued_at'], 'tenant_report_dispatches_assignment_queued_index');
            $table->index(['tenant_id', 'status', 'queued_at'], 'tenant_report_dispatches_tenant_status_queued_index');
            $table->index(['status', 'queued_at'], 'tenant_report_dispatches_status_queued_index');
            $table->index(['tenant_report_run_uuid'], 'tenant_report_dispatches_run_uuid_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE report_schedule_templates ADD CONSTRAINT report_schedule_templates_area_check CHECK (report_area IN ('sales', 'payments', 'inventory', 'procurement', 'finance', 'tax', 'payroll', 'staff', 'customers', 'operations', 'audit'))");
        $connection->statement("ALTER TABLE report_schedule_templates ADD CONSTRAINT report_schedule_templates_frequency_check CHECK (frequency IN ('daily', 'weekly', 'monthly', 'quarterly'))");
        $connection->statement("ALTER TABLE report_schedule_templates ADD CONSTRAINT report_schedule_templates_timezone_strategy_check CHECK (timezone_strategy IN ('tenant_timezone', 'fixed_timezone'))");
        $connection->statement("ALTER TABLE report_schedule_templates ADD CONSTRAINT report_schedule_templates_format_check CHECK (export_format IN ('pdf', 'xlsx', 'csv'))");
        $connection->statement("ALTER TABLE report_schedule_templates ADD CONSTRAINT report_schedule_templates_status_check CHECK (status IN ('active', 'inactive', 'retired'))");
        $connection->statement('ALTER TABLE report_schedule_templates ADD CONSTRAINT report_schedule_templates_priority_check CHECK (priority BETWEEN 1 AND 9)');
        $connection->statement("ALTER TABLE tenant_report_schedule_assignments ADD CONSTRAINT tenant_report_assignments_frequency_check CHECK (frequency IN ('daily', 'weekly', 'monthly', 'quarterly'))");
        $connection->statement("ALTER TABLE tenant_report_schedule_assignments ADD CONSTRAINT tenant_report_assignments_format_check CHECK (export_format IN ('pdf', 'xlsx', 'csv'))");
        $connection->statement("ALTER TABLE tenant_report_schedule_assignments ADD CONSTRAINT tenant_report_assignments_status_check CHECK (status IN ('active', 'paused', 'ended', 'failed'))");
        $connection->statement('ALTER TABLE tenant_report_schedule_assignments ADD CONSTRAINT tenant_report_assignments_priority_check CHECK (priority BETWEEN 1 AND 9)');
        $connection->statement('ALTER TABLE tenant_report_schedule_assignments ADD CONSTRAINT tenant_report_assignments_lock_version_check CHECK (lock_version > 0)');
        $connection->statement('CREATE UNIQUE INDEX tenant_report_assignments_company_template_unique ON tenant_report_schedule_assignments (tenant_id, report_schedule_template_id) WHERE tenant_branch_uuid IS NULL');
        $connection->statement('CREATE UNIQUE INDEX tenant_report_assignments_branch_template_unique ON tenant_report_schedule_assignments (tenant_id, report_schedule_template_id, tenant_branch_uuid) WHERE tenant_branch_uuid IS NOT NULL');
        $connection->statement("ALTER TABLE tenant_report_schedule_recipients ADD CONSTRAINT tenant_report_recipients_type_check CHECK (recipient_type IN ('tenant_primary_contact', 'tenant_user', 'platform_user', 'external_contact'))");
        $connection->statement("ALTER TABLE tenant_report_schedule_recipients ADD CONSTRAINT tenant_report_recipients_channel_check CHECK (channel IN ('email', 'sms', 'whatsapp', 'push', 'in_app'))");
        $connection->statement("ALTER TABLE tenant_report_schedule_recipients ADD CONSTRAINT tenant_report_recipients_contact_check CHECK ((recipient_type = 'tenant_primary_contact') OR (recipient_type = 'tenant_user' AND tenant_user_uuid IS NOT NULL) OR (recipient_type = 'platform_user' AND platform_user_id IS NOT NULL) OR (recipient_type = 'external_contact' AND (email_snapshot IS NOT NULL OR phone_snapshot IS NOT NULL)))");
        $connection->statement("CREATE UNIQUE INDEX tenant_report_recipients_tenant_user_unique ON tenant_report_schedule_recipients (tenant_report_schedule_assignment_id, tenant_user_uuid, channel) WHERE tenant_user_uuid IS NOT NULL");
        $connection->statement("ALTER TABLE tenant_report_schedule_dispatches ADD CONSTRAINT tenant_report_dispatches_status_check CHECK (status IN ('queued', 'dispatched', 'completed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE tenant_report_schedule_dispatches ADD CONSTRAINT tenant_report_dispatches_dates_check CHECK ((dispatched_at IS NULL OR dispatched_at >= queued_at) AND (completed_at IS NULL OR dispatched_at IS NULL OR completed_at >= dispatched_at))');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS tenant_report_recipients_tenant_user_unique');
        $connection->statement('DROP INDEX IF EXISTS tenant_report_assignments_branch_template_unique');
        $connection->statement('DROP INDEX IF EXISTS tenant_report_assignments_company_template_unique');

        $schema->dropIfExists('tenant_report_schedule_dispatches');
        $schema->dropIfExists('tenant_report_schedule_recipients');
        $schema->dropIfExists('tenant_report_schedule_assignments');
        $schema->dropIfExists('report_schedule_templates');
    }
};
