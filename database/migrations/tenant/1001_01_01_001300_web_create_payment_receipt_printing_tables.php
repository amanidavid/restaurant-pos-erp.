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

        if ($schema->hasTable('payments')) {
            return;
        }

        $schema->create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('method_type', 24);
            $table->boolean('requires_provider')->default(false);
            $table->boolean('allows_change')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'payment_methods_company_name_unique');
            $table->index(['company_id', 'method_type', 'status'], 'payment_methods_company_type_status_index');
        });

        $schema->create('integration_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('provider_type', 32);
            $table->string('status', 24)->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'integration_providers_company_name_unique');
            $table->index(['company_id', 'provider_type', 'status'], 'integration_providers_company_type_status_index');
        });

        $schema->create('provider_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_provider_id')->constrained('integration_providers')->cascadeOnDelete();
            $table->string('capability', 40);
            $table->boolean('is_enabled')->default(true);
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['integration_provider_id', 'capability'],
                'provider_capabilities_provider_capability_unique'
            );
            $table->index(['capability', 'is_enabled'], 'provider_capabilities_capability_enabled_index');
        });

        $schema->create('integration_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('integration_provider_id')->constrained('integration_providers')->restrictOnDelete();
            $table->string('account_name', 140);
            $table->string('external_account_reference', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 24)->default('active');
            $table->jsonb('encrypted_credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'integration_provider_id', 'account_name'],
                'integration_accounts_company_provider_name_unique'
            );
            $table->index(['company_id', 'status'], 'integration_accounts_company_status_index');
            $table->index(['branch_id', 'status'], 'integration_accounts_branch_status_index');
            $table->index(
                ['integration_provider_id', 'status'],
                'integration_accounts_provider_status_index'
            );
        });

        $schema->create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->nullOnDelete();
            $table->foreignId('initiated_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('idempotency_key', 120);
            $table->string('provider_request_reference', 120)->nullable();
            $table->string('customer_phone_snapshot', 20)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('pending');
            $table->timestampTz('initiated_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'idempotency_key'], 'payment_attempts_branch_idempotency_unique');
            $table->index(['order_id', 'status'], 'payment_attempts_order_status_index');
            $table->index(['branch_id', 'status', 'initiated_at'], 'payment_attempts_branch_status_initiated_index');
            $table->index(['integration_account_id', 'status'], 'payment_attempts_account_status_index');
        });

        $schema->create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('business_date_id')->nullable()->constrained('business_dates')->nullOnDelete();
            $table->foreignId('shift_session_id')->nullable()->constrained('shift_sessions')->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->foreignId('received_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('completed');
            $table->timestampTz('paid_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['order_id', 'status'], 'payments_order_status_index');
            $table->index(['branch_id', 'status', 'paid_at'], 'payments_branch_status_paid_index');
            $table->index(['business_date_id', 'status'], 'payments_business_date_status_index');
            $table->index(['payment_method_id', 'paid_at'], 'payments_method_paid_index');
        });

        $schema->create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestampsTz();

            $table->unique(['payment_id', 'order_id'], 'payment_allocations_payment_order_unique');
            $table->index(['order_id'], 'payment_allocations_order_index');
            $table->index(['receipt_id'], 'payment_allocations_receipt_index');
        });

        $schema->create('provider_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_account_id')->constrained('integration_accounts')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider_transaction_reference', 160);
            $table->string('provider_status', 40);
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3);
            $table->timestampTz('provider_created_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['integration_account_id', 'provider_transaction_reference'],
                'provider_transactions_account_reference_unique'
            );
            $table->index(['payment_attempt_id'], 'provider_transactions_attempt_index');
            $table->index(['payment_id'], 'provider_transactions_payment_index');
            $table->index(
                ['integration_account_id', 'provider_status', 'provider_created_at'],
                'provider_transactions_account_status_created_index'
            );
        });

        $schema->create('webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_account_id')->constrained('integration_accounts')->restrictOnDelete();
            $table->string('event_id', 160)->nullable();
            $table->string('event_type', 80);
            $table->string('signature_status', 24)->default('pending');
            $table->string('processing_status', 24)->default('pending');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->jsonb('headers')->nullable();
            $table->jsonb('payload');
            $table->text('processing_error')->nullable();
            $table->timestampsTz();

            $table->index(['integration_account_id', 'processing_status', 'received_at'], 'webhooks_account_status_received_index');
            $table->index(['event_type', 'received_at'], 'webhooks_event_received_index');
        });

        $schema->create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('refund_number', 40)->unique();
            $table->decimal('amount', 18, 2);
            $table->string('status', 24)->default('pending');
            $table->string('reason', 180)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['payment_id', 'status'], 'refunds_payment_status_index');
            $table->index(['branch_id', 'status', 'requested_at'], 'refunds_branch_status_requested_index');
        });

        $schema->create('provider_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_account_id')->constrained('integration_accounts')->restrictOnDelete();
            $table->string('settlement_reference', 160);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->date('settlement_date');
            $table->string('status', 24)->default('pending');
            $table->jsonb('raw_payload')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['integration_account_id', 'settlement_reference'],
                'provider_settlements_account_reference_unique'
            );
            $table->index(
                ['integration_account_id', 'status', 'settlement_date'],
                'provider_settlements_account_status_date_index'
            );
        });

        $schema->create('provider_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('provider_settlement_id')->constrained('provider_settlements')->cascadeOnDelete();
            $table->foreignId('provider_transaction_id')->nullable()->constrained('provider_transactions')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('provider_amount', 18, 2);
            $table->decimal('internal_amount', 18, 2);
            $table->decimal('difference_amount', 18, 2)->default(0);
            $table->string('status', 24)->default('matched');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['provider_settlement_id', 'status'], 'provider_recons_settlement_status_index');
            $table->index(['provider_transaction_id'], 'provider_recons_transaction_index');
            $table->index(['payment_id'], 'provider_recons_payment_index');
        });

        $schema->create('aggregator_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_account_id')->constrained('integration_accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('external_order_reference', 160);
            $table->string('customer_name_snapshot', 180)->nullable();
            $table->string('customer_phone_snapshot', 20)->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('received');
            $table->timestampTz('received_at');
            $table->jsonb('raw_payload')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['integration_account_id', 'external_order_reference'],
                'aggregator_orders_account_reference_unique'
            );
            $table->index(['branch_id', 'status', 'received_at'], 'aggregator_orders_branch_status_received_index');
            $table->index(['order_id'], 'aggregator_orders_order_index');
        });

        $schema->create('aggregator_order_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('aggregator_order_id')->constrained('aggregator_orders')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->timestampTz('event_at');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['aggregator_order_id', 'event_at'], 'aggregator_events_order_event_at_index');
            $table->index(['event_type', 'event_at'], 'aggregator_events_type_event_at_index');
        });

        $schema->table('tips', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->after('order_id')->constrained('payments')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('payment_id')->constrained('branches')->restrictOnDelete();
            $table->string('tip_source', 24)->default('order')->after('tenant_user_id');
            $table->string('distribution_status', 24)->default('pending')->after('tip_source');

            $table->index(['branch_id', 'distribution_status'], 'tips_branch_distribution_status_index');
            $table->index(['payment_id'], 'tips_payment_index');
        });

        $schema->create('tip_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tip_id')->constrained('tips')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('status', 24)->default('pending');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tip_id', 'tenant_user_id'], 'tip_allocations_tip_user_unique');
            $table->index(['tenant_user_id', 'status'], 'tip_allocations_user_status_index');
            $table->index(['payroll_run_id', 'status'], 'tip_allocations_payroll_status_index');
        });

        $schema->create('print_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('device_type', 32);
            $table->string('connection_type', 32);
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('device_identifier', 160)->nullable();
            $table->unsignedSmallInteger('paper_width_mm')->default(80);
            $table->boolean('is_default_receipt_printer')->default(false);
            $table->boolean('is_default_bill_printer')->default(false);
            $table->boolean('is_default_kitchen_printer')->default(false);
            $table->string('status', 24)->default('active');
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'print_devices_branch_name_unique');
            $table->index(['branch_id', 'device_type', 'status'], 'print_devices_branch_type_status_index');
            $table->index(['branch_id', 'connection_type', 'status'], 'print_devices_branch_connection_status_index');
        });

        $schema->create('print_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('template_type', 32);
            $table->unsignedSmallInteger('paper_width_mm')->default(80);
            $table->boolean('is_default')->default(false);
            $table->string('status', 24)->default('active');
            $table->jsonb('layout');
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'name'], 'print_templates_company_branch_name_unique');
            $table->index(['company_id', 'template_type', 'status'], 'print_templates_company_type_status_index');
            $table->index(['branch_id', 'template_type', 'status'], 'print_templates_branch_type_status_index');
        });

        $schema->create('print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('print_device_id')->nullable()->constrained('print_devices')->nullOnDelete();
            $table->foreignId('print_template_id')->nullable()->constrained('print_templates')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->cascadeOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('kitchen_ticket_id')->nullable()->constrained('kitchen_tickets')->cascadeOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('job_type', 32);
            $table->string('status', 24)->default('pending');
            $table->jsonb('payload');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('requested_at');
            $table->timestampTz('printed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status', 'requested_at'], 'print_jobs_branch_status_requested_index');
            $table->index(['print_device_id', 'status', 'requested_at'], 'print_jobs_device_status_requested_index');
            $table->index(['order_id', 'job_type'], 'print_jobs_order_type_index');
            $table->index(['receipt_id'], 'print_jobs_receipt_index');
            $table->index(['kitchen_ticket_id'], 'print_jobs_kitchen_ticket_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check CHECK (method_type IN ('cash', 'card', 'mobile_money', 'bank_transfer', 'aggregator', 'voucher', 'wallet'))");
        $connection->statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE integration_providers ADD CONSTRAINT integration_providers_type_check CHECK (provider_type IN ('payment', 'aggregator', 'sms', 'email', 'whatsapp', 'push', 'tax', 'accounting', 'delivery'))");
        $connection->statement("ALTER TABLE integration_providers ADD CONSTRAINT integration_providers_status_check CHECK (status IN ('active', 'inactive', 'suspended'))");
        $connection->statement("ALTER TABLE provider_capabilities ADD CONSTRAINT provider_capabilities_capability_check CHECK (capability IN ('payment_collection', 'refunds', 'webhooks', 'settlements', 'reconciliation', 'order_import', 'order_status_sync', 'sms_send', 'sms_delivery_callback', 'email_send', 'email_delivery_callback', 'whatsapp_send', 'whatsapp_delivery_callback', 'push_send'))");
        $connection->statement("ALTER TABLE integration_accounts ADD CONSTRAINT integration_accounts_status_check CHECK (status IN ('active', 'inactive', 'suspended'))");
        $connection->statement("CREATE UNIQUE INDEX integration_accounts_company_default_unique ON integration_accounts (company_id, integration_provider_id) WHERE is_default = true AND branch_id IS NULL AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX integration_accounts_branch_default_unique ON integration_accounts (branch_id, integration_provider_id) WHERE is_default = true AND branch_id IS NOT NULL AND status = 'active'");
        $connection->statement("ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_status_check CHECK (status IN ('pending', 'processing', 'authorized', 'completed', 'failed', 'cancelled', 'expired'))");
        $connection->statement('ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_amount_check CHECK (amount > 0)');
        $connection->statement('ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_dates_check CHECK (completed_at IS NULL OR completed_at >= initiated_at)');
        $connection->statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('completed', 'voided', 'refunded', 'partially_refunded'))");
        $connection->statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0 AND change_amount >= 0)');
        $connection->statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_check CHECK (amount > 0)');
        $connection->statement('ALTER TABLE provider_transactions ADD CONSTRAINT provider_transactions_amount_check CHECK (amount >= 0)');
        $connection->statement("ALTER TABLE webhook_receipts ADD CONSTRAINT webhook_receipts_signature_status_check CHECK (signature_status IN ('pending', 'valid', 'invalid', 'not_required'))");
        $connection->statement("ALTER TABLE webhook_receipts ADD CONSTRAINT webhook_receipts_processing_status_check CHECK (processing_status IN ('pending', 'processed', 'failed', 'ignored'))");
        $connection->statement("CREATE UNIQUE INDEX webhook_receipts_account_event_unique ON webhook_receipts (integration_account_id, event_id) WHERE event_id IS NOT NULL");
        $connection->statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('pending', 'approved', 'processing', 'completed', 'rejected', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
        $connection->statement('ALTER TABLE refunds ADD CONSTRAINT refunds_dates_check CHECK (approved_at IS NULL OR approved_at >= requested_at)');
        $connection->statement("ALTER TABLE provider_settlements ADD CONSTRAINT provider_settlements_status_check CHECK (status IN ('pending', 'imported', 'reconciled', 'disputed'))");
        $connection->statement('ALTER TABLE provider_settlements ADD CONSTRAINT provider_settlements_amounts_check CHECK (gross_amount >= 0 AND fee_amount >= 0 AND net_amount >= 0)');
        $connection->statement("ALTER TABLE provider_reconciliations ADD CONSTRAINT provider_reconciliations_status_check CHECK (status IN ('matched', 'underpaid', 'overpaid', 'missing_internal', 'missing_provider', 'disputed'))");
        $connection->statement("ALTER TABLE aggregator_orders ADD CONSTRAINT aggregator_orders_status_check CHECK (status IN ('received', 'accepted', 'rejected', 'preparing', 'ready', 'dispatched', 'completed', 'cancelled'))");
        $connection->statement('ALTER TABLE aggregator_orders ADD CONSTRAINT aggregator_orders_total_amount_check CHECK (total_amount >= 0)');
        $connection->statement("ALTER TABLE tips ADD CONSTRAINT tips_source_check CHECK (tip_source IN ('order', 'cash', 'card', 'mobile_money', 'aggregator'))");
        $connection->statement("ALTER TABLE tips ADD CONSTRAINT tips_distribution_status_check CHECK (distribution_status IN ('pending', 'allocated', 'paid', 'cancelled'))");
        $connection->statement("ALTER TABLE tip_allocations ADD CONSTRAINT tip_allocations_status_check CHECK (status IN ('pending', 'approved', 'paid', 'cancelled'))");
        $connection->statement('ALTER TABLE tip_allocations ADD CONSTRAINT tip_allocations_amount_check CHECK (amount > 0)');
        $connection->statement("ALTER TABLE print_devices ADD CONSTRAINT print_devices_type_check CHECK (device_type IN ('thermal_receipt', 'kitchen_printer', 'bar_printer', 'label_printer'))");
        $connection->statement("ALTER TABLE print_devices ADD CONSTRAINT print_devices_connection_type_check CHECK (connection_type IN ('network', 'usb', 'bluetooth', 'browser_agent'))");
        $connection->statement("ALTER TABLE print_devices ADD CONSTRAINT print_devices_status_check CHECK (status IN ('active', 'inactive', 'maintenance'))");
        $connection->statement('ALTER TABLE print_devices ADD CONSTRAINT print_devices_values_check CHECK (paper_width_mm IN (58, 80) AND (port IS NULL OR port BETWEEN 1 AND 65535))');
        $connection->statement("CREATE UNIQUE INDEX print_devices_default_receipt_unique ON print_devices (branch_id) WHERE is_default_receipt_printer = true AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX print_devices_default_bill_unique ON print_devices (branch_id) WHERE is_default_bill_printer = true AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX print_devices_default_kitchen_unique ON print_devices (branch_id) WHERE is_default_kitchen_printer = true AND status = 'active'");
        $connection->statement("ALTER TABLE print_templates ADD CONSTRAINT print_templates_type_check CHECK (template_type IN ('receipt', 'bill', 'kitchen_ticket', 'refund', 'payment'))");
        $connection->statement("ALTER TABLE print_templates ADD CONSTRAINT print_templates_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE print_templates ADD CONSTRAINT print_templates_width_check CHECK (paper_width_mm IN (58, 80))');
        $connection->statement("CREATE UNIQUE INDEX print_templates_company_default_unique ON print_templates (company_id, template_type, paper_width_mm) WHERE is_default = true AND branch_id IS NULL AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX print_templates_branch_default_unique ON print_templates (branch_id, template_type, paper_width_mm) WHERE is_default = true AND branch_id IS NOT NULL AND status = 'active'");
        $connection->statement("ALTER TABLE print_jobs ADD CONSTRAINT print_jobs_type_check CHECK (job_type IN ('receipt', 'bill', 'kitchen_ticket', 'refund', 'payment'))");
        $connection->statement("ALTER TABLE print_jobs ADD CONSTRAINT print_jobs_status_check CHECK (status IN ('pending', 'printing', 'printed', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE print_jobs ADD CONSTRAINT print_jobs_reference_check CHECK (num_nonnulls(order_id, receipt_id, payment_id, refund_id, kitchen_ticket_id) >= 1)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS print_templates_branch_default_unique');
        $connection->statement('DROP INDEX IF EXISTS print_templates_company_default_unique');
        $connection->statement('DROP INDEX IF EXISTS print_devices_default_kitchen_unique');
        $connection->statement('DROP INDEX IF EXISTS print_devices_default_bill_unique');
        $connection->statement('DROP INDEX IF EXISTS print_devices_default_receipt_unique');
        $connection->statement('DROP INDEX IF EXISTS webhook_receipts_account_event_unique');
        $connection->statement('DROP INDEX IF EXISTS integration_accounts_branch_default_unique');
        $connection->statement('DROP INDEX IF EXISTS integration_accounts_company_default_unique');

        $schema->dropIfExists('print_jobs');
        $schema->dropIfExists('print_templates');
        $schema->dropIfExists('print_devices');
        $schema->dropIfExists('tip_allocations');

        if ($schema->hasColumn('tips', 'payment_id')) {
            $schema->table('tips', function (Blueprint $table): void {
                $table->dropIndex('tips_branch_distribution_status_index');
                $table->dropIndex('tips_payment_index');
                $table->dropConstrainedForeignId('payment_id');
                $table->dropConstrainedForeignId('branch_id');
                $table->dropColumn(['tip_source', 'distribution_status']);
            });
        }

        $schema->dropIfExists('aggregator_order_events');
        $schema->dropIfExists('aggregator_orders');
        $schema->dropIfExists('provider_reconciliations');
        $schema->dropIfExists('provider_settlements');
        $schema->dropIfExists('refunds');
        $schema->dropIfExists('webhook_receipts');
        $schema->dropIfExists('provider_transactions');
        $schema->dropIfExists('payment_allocations');
        $schema->dropIfExists('payments');
        $schema->dropIfExists('payment_attempts');
        $schema->dropIfExists('integration_accounts');
        $schema->dropIfExists('provider_capabilities');
        $schema->dropIfExists('integration_providers');
        $schema->dropIfExists('payment_methods');
    }
};
