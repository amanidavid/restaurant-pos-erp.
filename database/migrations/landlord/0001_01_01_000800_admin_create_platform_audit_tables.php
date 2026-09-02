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

        if ($schema->hasTable('platform_audit_events')) {
            return;
        }

        $schema->create('platform_audit_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('audit_area', 40);
            $table->string('event_type', 80);
            $table->string('severity', 16)->default('info');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('retention_days')->default(730);
            $table->boolean('requires_reason')->default(false);
            $table->boolean('capture_before_after')->default(false);
            $table->timestampsTz();

            $table->unique(['audit_area', 'event_type'], 'platform_audit_policies_area_event_unique');
            $table->index(['is_enabled', 'audit_area'], 'platform_audit_policies_enabled_area_index');
        });

        $schema->create('platform_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_audit_policy_id')->nullable()->constrained('platform_audit_policies')->nullOnDelete();
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->constrained('subscription_invoices')->nullOnDelete();
            $table->foreignId('tenant_feature_override_id')->nullable()->constrained('tenant_feature_overrides')->nullOnDelete();
            $table->foreignId('report_schedule_template_id')->nullable()->constrained('report_schedule_templates')->nullOnDelete();
            $table->foreignId('tenant_report_schedule_assignment_id')->nullable()->constrained('tenant_report_schedule_assignments')->nullOnDelete();
            $table->foreignId('platform_setting_id')->nullable()->constrained('platform_settings')->nullOnDelete();
            $table->string('audit_area', 40);
            $table->string('event_type', 80);
            $table->string('severity', 16)->default('info');
            $table->string('request_id', 80)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('changed_fields')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('previous_event_hash', 128)->nullable();
            $table->string('event_hash', 128)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'audit_area', 'occurred_at'], 'platform_audit_events_tenant_area_occurred_index');
            $table->index(['platform_user_id', 'occurred_at'], 'platform_audit_events_user_occurred_index');
            $table->index(['audit_area', 'event_type', 'occurred_at'], 'platform_audit_events_area_type_occurred_index');
            $table->index(['severity', 'occurred_at'], 'platform_audit_events_severity_occurred_index');
            $table->index(['request_id'], 'platform_audit_events_request_index');
            $table->index(['event_hash'], 'platform_audit_events_hash_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE platform_audit_policies ADD CONSTRAINT platform_audit_policies_area_check CHECK (audit_area IN ('tenant', 'database', 'subscription', 'feature', 'billing', 'platform_user', 'setting', 'report_schedule', 'backup', 'restore', 'security'))");
        $connection->statement("ALTER TABLE platform_audit_policies ADD CONSTRAINT platform_audit_policies_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");
        $connection->statement('ALTER TABLE platform_audit_policies ADD CONSTRAINT platform_audit_policies_retention_check CHECK (retention_days BETWEEN 30 AND 3650)');
        $connection->statement("ALTER TABLE platform_audit_events ADD CONSTRAINT platform_audit_events_area_check CHECK (audit_area IN ('tenant', 'database', 'subscription', 'feature', 'billing', 'platform_user', 'setting', 'report_schedule', 'backup', 'restore', 'security'))");
        $connection->statement("ALTER TABLE platform_audit_events ADD CONSTRAINT platform_audit_events_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");
        $connection->statement('ALTER TABLE platform_audit_events ADD CONSTRAINT platform_audit_events_reference_check CHECK (num_nonnulls(tenant_id, tenant_database_id, tenant_subscription_id, subscription_invoice_id, tenant_feature_override_id, report_schedule_template_id, tenant_report_schedule_assignment_id, platform_setting_id) >= 1)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('platform_audit_events');
        $schema->dropIfExists('platform_audit_policies');
    }
};
