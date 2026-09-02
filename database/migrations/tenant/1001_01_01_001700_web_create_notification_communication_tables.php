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

        if ($schema->hasTable('notification_messages')) {
            return;
        }

        $schema->create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('event_type', 60);
            $table->string('channel', 24);
            $table->string('subject_template', 180)->nullable();
            $table->text('body_template');
            $table->string('locale', 16)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'branch_id', 'event_type', 'channel', 'locale'],
                'notification_templates_scope_event_channel_locale_unique'
            );
            $table->index(['company_id', 'event_type', 'channel', 'is_active'], 'notification_templates_company_event_channel_active_index');
            $table->index(['branch_id', 'event_type', 'channel', 'is_active'], 'notification_templates_branch_event_channel_active_index');
        });

        $schema->create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('event_type', 60);
            $table->string('recipient_type', 40);
            $table->string('channel', 24);
            $table->boolean('is_active')->default(true);
            $table->jsonb('conditions')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'name'], 'notification_rules_company_branch_name_unique');
            $table->index(['company_id', 'event_type', 'is_active'], 'notification_rules_company_event_active_index');
            $table->index(['branch_id', 'event_type', 'is_active'], 'notification_rules_branch_event_active_index');
            $table->index(['permission_id'], 'notification_rules_permission_index');
        });

        $schema->create('notification_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('created_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('fiscal_receipt_id')->nullable()->constrained('fiscal_receipts')->nullOnDelete();
            $table->foreignId('tax_filing_submission_id')->nullable()->constrained('tax_filing_submissions')->nullOnDelete();
            $table->string('system_reference_key', 120)->nullable();
            $table->string('event_type', 60);
            $table->string('title', 180);
            $table->text('body');
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('queued');
            $table->timestampTz('queued_at');
            $table->timestampTz('processed_at')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'event_type', 'status', 'queued_at'], 'notification_messages_company_event_status_queued_index');
            $table->index(['branch_id', 'event_type', 'status', 'queued_at'], 'notification_messages_branch_event_status_queued_index');
            $table->index(['approval_request_id'], 'notification_messages_approval_request_index');
            $table->index(['order_id'], 'notification_messages_order_index');
            $table->index(['reservation_id'], 'notification_messages_reservation_index');
            $table->index(['payment_id'], 'notification_messages_payment_index');
            $table->index(['refund_id'], 'notification_messages_refund_index');
            $table->index(['purchase_order_id'], 'notification_messages_purchase_order_index');
            $table->index(['stock_batch_id'], 'notification_messages_stock_batch_index');
            $table->index(['payroll_run_id'], 'notification_messages_payroll_run_index');
            $table->index(['fiscal_receipt_id'], 'notification_messages_fiscal_receipt_index');
            $table->index(['tax_filing_submission_id'], 'notification_messages_tax_filing_index');
            $table->index(['system_reference_key', 'queued_at'], 'notification_messages_system_reference_queued_index');
        });

        $schema->create('notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('notification_message_id')->constrained('notification_messages')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('recipient_name_snapshot', 180)->nullable();
            $table->string('email_snapshot', 254)->nullable();
            $table->string('phone_snapshot', 20)->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['notification_message_id', 'status'], 'notification_recipients_message_status_index');
            $table->index(['tenant_user_id', 'status', 'read_at'], 'notification_recipients_user_status_read_index');
            $table->index(['customer_id', 'status'], 'notification_recipients_customer_status_index');
        });

        $schema->create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('notification_message_id')->constrained('notification_messages')->cascadeOnDelete();
            $table->foreignId('notification_recipient_id')->constrained('notification_recipients')->cascadeOnDelete();
            $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->nullOnDelete();
            $table->string('channel', 24);
            $table->string('destination', 254);
            $table->string('provider_message_reference', 160)->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['notification_recipient_id', 'channel', 'destination'],
                'notification_deliveries_recipient_channel_destination_unique'
            );
            $table->index(['channel', 'status', 'last_attempt_at'], 'notification_deliveries_channel_status_attempt_index');
            $table->index(['integration_account_id', 'status', 'last_attempt_at'], 'notification_deliveries_account_status_attempt_index');
            $table->index(['provider_message_reference'], 'notification_deliveries_provider_reference_index');
        });

        $schema->create('notification_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('notification_delivery_id')->constrained('notification_deliveries')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->timestampTz('event_at');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['notification_delivery_id', 'event_at'], 'notification_delivery_events_delivery_event_at_index');
            $table->index(['event_type', 'event_at'], 'notification_delivery_events_type_event_at_index');
        });

        $schema->create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('channel', 24);
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'tenant_user_id', 'customer_id', 'event_type', 'channel'],
                'notification_preferences_recipient_event_channel_unique'
            );
            $table->index(['tenant_user_id', 'event_type', 'is_enabled'], 'notification_preferences_user_event_enabled_index');
            $table->index(['customer_id', 'event_type', 'is_enabled'], 'notification_preferences_customer_event_enabled_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE notification_templates ADD CONSTRAINT notification_templates_channel_check CHECK (channel IN ('in_app', 'sms', 'email', 'whatsapp', 'push'))");
        $connection->statement("ALTER TABLE notification_rules ADD CONSTRAINT notification_rules_channel_check CHECK (channel IN ('in_app', 'sms', 'email', 'whatsapp', 'push'))");
        $connection->statement("ALTER TABLE notification_rules ADD CONSTRAINT notification_rules_recipient_type_check CHECK (recipient_type IN ('requested_user', 'assigned_user', 'customer', 'branch_managers', 'permission_holders', 'accountants', 'owners', 'custom'))");
        $connection->statement("ALTER TABLE notification_messages ADD CONSTRAINT notification_messages_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))");
        $connection->statement("ALTER TABLE notification_messages ADD CONSTRAINT notification_messages_status_check CHECK (status IN ('queued', 'processing', 'sent', 'partially_sent', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE notification_messages ADD CONSTRAINT notification_messages_source_check CHECK (num_nonnulls(approval_request_id, order_id, reservation_id, payment_id, refund_id, purchase_order_id, stock_batch_id, payroll_run_id, fiscal_receipt_id, tax_filing_submission_id, system_reference_key) >= 1)');
        $connection->statement("ALTER TABLE notification_recipients ADD CONSTRAINT notification_recipients_status_check CHECK (status IN ('pending', 'sent', 'failed', 'read', 'cancelled'))");
        $connection->statement('ALTER TABLE notification_recipients ADD CONSTRAINT notification_recipients_target_check CHECK (num_nonnulls(tenant_user_id, customer_id) <= 1 AND (tenant_user_id IS NOT NULL OR customer_id IS NOT NULL OR email_snapshot IS NOT NULL OR phone_snapshot IS NOT NULL))');
        $connection->statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_channel_check CHECK (channel IN ('in_app', 'sms', 'email', 'whatsapp', 'push'))");
        $connection->statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_status_check CHECK (status IN ('pending', 'queued', 'accepted', 'delivered', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_attempt_check CHECK (attempt_count >= 0)');
        $connection->statement("ALTER TABLE notification_delivery_events ADD CONSTRAINT notification_delivery_events_type_check CHECK (event_type IN ('queued', 'accepted', 'delivered', 'failed', 'bounced', 'read', 'callback_received'))");
        $connection->statement("ALTER TABLE notification_preferences ADD CONSTRAINT notification_preferences_channel_check CHECK (channel IN ('in_app', 'sms', 'email', 'whatsapp', 'push'))");
        $connection->statement('ALTER TABLE notification_preferences ADD CONSTRAINT notification_preferences_target_check CHECK (num_nonnulls(tenant_user_id, customer_id) = 1)');
        $connection->statement("CREATE UNIQUE INDEX notification_deliveries_provider_reference_unique ON notification_deliveries (integration_account_id, provider_message_reference) WHERE integration_account_id IS NOT NULL AND provider_message_reference IS NOT NULL");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS notification_deliveries_provider_reference_unique');

        $schema->dropIfExists('notification_preferences');
        $schema->dropIfExists('notification_delivery_events');
        $schema->dropIfExists('notification_deliveries');
        $schema->dropIfExists('notification_recipients');
        $schema->dropIfExists('notification_messages');
        $schema->dropIfExists('notification_rules');
        $schema->dropIfExists('notification_templates');
    }
};
