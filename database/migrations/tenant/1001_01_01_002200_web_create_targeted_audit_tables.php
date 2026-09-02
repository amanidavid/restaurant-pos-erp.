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

        if ($schema->hasTable('audit_events')) {
            return;
        }

        $schema->create('audit_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('audit_area', 40);
            $table->string('event_type', 80);
            $table->string('severity', 16)->default('info');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_reason')->default(false);
            $table->boolean('capture_before_after')->default(false);
            $table->unsignedSmallInteger('retention_days')->default(730);
            $table->timestampsTz();

            $table->unique(['company_id', 'audit_area', 'event_type'], 'audit_policies_company_area_event_unique');
            $table->index(['company_id', 'is_enabled', 'audit_area'], 'audit_policies_company_enabled_area_index');
        });

        $schema->create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('audit_policy_id')->nullable()->constrained('audit_policies')->nullOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('affected_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
            $table->foreignId('stock_transfer_id')->nullable()->constrained('stock_transfers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('tax_transaction_id')->nullable()->constrained('tax_transactions')->nullOnDelete();
            $table->foreignId('fiscal_receipt_id')->nullable()->constrained('fiscal_receipts')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('business_document_id')->nullable()->constrained('business_documents')->nullOnDelete();
            $table->foreignId('data_import_batch_id')->nullable()->constrained('data_import_batches')->nullOnDelete();
            $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->nullOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
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

            $table->index(['company_id', 'audit_area', 'occurred_at'], 'audit_events_company_area_occurred_index');
            $table->index(['branch_id', 'audit_area', 'occurred_at'], 'audit_events_branch_area_occurred_index');
            $table->index(['tenant_user_id', 'occurred_at'], 'audit_events_user_occurred_index');
            $table->index(['affected_tenant_user_id', 'occurred_at'], 'audit_events_affected_user_occurred_index');
            $table->index(['audit_area', 'event_type', 'occurred_at'], 'audit_events_area_type_occurred_index');
            $table->index(['severity', 'occurred_at'], 'audit_events_severity_occurred_index');
            $table->index(['request_id'], 'audit_events_request_index');
            $table->index(['event_hash'], 'audit_events_hash_index');
            $table->index(['payment_id', 'occurred_at'], 'audit_events_payment_occurred_index');
            $table->index(['refund_id', 'occurred_at'], 'audit_events_refund_occurred_index');
            $table->index(['payroll_run_id', 'occurred_at'], 'audit_events_payroll_occurred_index');
            $table->index(['journal_entry_id', 'occurred_at'], 'audit_events_journal_occurred_index');
            $table->index(['stock_adjustment_id', 'occurred_at'], 'audit_events_stock_adjustment_occurred_index');
            $table->index(['approval_request_id', 'occurred_at'], 'audit_events_approval_occurred_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE audit_policies ADD CONSTRAINT audit_policies_area_check CHECK (audit_area IN ('security', 'access', 'configuration', 'integration', 'sales', 'payment', 'refund', 'inventory', 'procurement', 'finance', 'payroll', 'tax', 'fiscal', 'approval', 'document', 'import', 'device', 'backup', 'restore'))");
        $connection->statement("ALTER TABLE audit_policies ADD CONSTRAINT audit_policies_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");
        $connection->statement('ALTER TABLE audit_policies ADD CONSTRAINT audit_policies_retention_check CHECK (retention_days BETWEEN 30 AND 3650)');
        $connection->statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_area_check CHECK (audit_area IN ('security', 'access', 'configuration', 'integration', 'sales', 'payment', 'refund', 'inventory', 'procurement', 'finance', 'payroll', 'tax', 'fiscal', 'approval', 'document', 'import', 'device', 'backup', 'restore'))");
        $connection->statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");
        $connection->statement('ALTER TABLE audit_events ADD CONSTRAINT audit_events_reference_check CHECK (num_nonnulls(affected_tenant_user_id, order_id, payment_id, refund_id, journal_entry_id, supplier_bill_id, payroll_run_id, stock_movement_id, stock_adjustment_id, stock_transfer_id, purchase_order_id, goods_receipt_id, tax_transaction_id, fiscal_receipt_id, approval_request_id, business_document_id, data_import_batch_id, integration_account_id, branch_device_id) >= 1)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('audit_events');
        $schema->dropIfExists('audit_policies');
    }
};
