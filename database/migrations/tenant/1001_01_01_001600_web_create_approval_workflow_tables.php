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

        if ($schema->hasTable('approval_requests')) {
            return;
        }

        $schema->create('approval_workflows', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name', 140);
            $table->string('workflow_area', 40);
            $table->decimal('minimum_amount', 18, 2)->nullable();
            $table->decimal('maximum_amount', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'name'], 'approval_workflows_company_branch_name_unique');
            $table->index(['company_id', 'workflow_area', 'is_active'], 'approval_workflows_company_area_active_index');
            $table->index(['branch_id', 'workflow_area', 'is_active'], 'approval_workflows_branch_area_active_index');
        });

        $schema->create('approval_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->restrictOnDelete();
            $table->unsignedSmallInteger('step_number');
            $table->string('name', 120);
            $table->unsignedSmallInteger('required_approval_count')->default(1);
            $table->boolean('can_self_approve')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['approval_workflow_id', 'step_number'], 'approval_workflow_steps_workflow_step_unique');
            $table->index(['approval_workflow_id', 'is_active'], 'approval_workflow_steps_workflow_active_index');
            $table->index(['permission_id'], 'approval_workflow_steps_permission_index');
        });

        $schema->create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('discount_application_id')->nullable()->constrained('discount_applications')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
            $table->foreignId('stock_transfer_id')->nullable()->constrained('stock_transfers')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->foreignId('expense_claim_id')->nullable()->constrained('expense_claims')->nullOnDelete();
            $table->foreignId('fiscal_receipt_id')->nullable()->constrained('fiscal_receipts')->nullOnDelete();
            $table->foreignId('tax_filing_submission_id')->nullable()->constrained('tax_filing_submissions')->nullOnDelete();
            $table->string('request_title', 160);
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('current_step_number')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status', 'submitted_at'], 'approval_requests_company_status_submitted_index');
            $table->index(['branch_id', 'status', 'submitted_at'], 'approval_requests_branch_status_submitted_index');
            $table->index(['approval_workflow_id', 'status'], 'approval_requests_workflow_status_index');
            $table->index(['requested_by_tenant_user_id', 'submitted_at'], 'approval_requests_requester_submitted_index');
            $table->index(['order_id'], 'approval_requests_order_index');
            $table->index(['receipt_id'], 'approval_requests_receipt_index');
            $table->index(['refund_id'], 'approval_requests_refund_index');
            $table->index(['purchase_order_id'], 'approval_requests_purchase_order_index');
            $table->index(['stock_adjustment_id'], 'approval_requests_stock_adjustment_index');
            $table->index(['stock_transfer_id'], 'approval_requests_stock_transfer_index');
            $table->index(['payroll_run_id'], 'approval_requests_payroll_run_index');
            $table->index(['journal_entry_id'], 'approval_requests_journal_entry_index');
            $table->index(['supplier_bill_id'], 'approval_requests_supplier_bill_index');
            $table->index(['expense_claim_id'], 'approval_requests_expense_claim_index');
            $table->index(['fiscal_receipt_id'], 'approval_requests_fiscal_receipt_index');
            $table->index(['tax_filing_submission_id'], 'approval_requests_tax_filing_index');
        });

        $schema->create('approval_request_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->unsignedSmallInteger('step_number');
            $table->string('name_snapshot', 120);
            $table->unsignedSmallInteger('required_approval_count_snapshot')->default(1);
            $table->string('status', 24)->default('pending');
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['approval_request_id', 'step_number'], 'approval_request_steps_request_step_unique');
            $table->index(['approval_request_id', 'status'], 'approval_request_steps_request_status_index');
            $table->index(['approval_workflow_step_id'], 'approval_request_steps_workflow_step_index');
        });

        $schema->create('approval_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_request_step_id')->constrained('approval_request_steps')->cascadeOnDelete();
            $table->foreignId('decided_by_tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->string('decision', 24);
            $table->text('comments')->nullable();
            $table->timestampTz('decided_at');
            $table->timestampsTz();

            $table->unique(
                ['approval_request_step_id', 'decided_by_tenant_user_id'],
                'approval_decisions_step_user_unique'
            );
            $table->index(['approval_request_id', 'decision', 'decided_at'], 'approval_decisions_request_decision_date_index');
            $table->index(['decided_by_tenant_user_id', 'decided_at'], 'approval_decisions_user_date_index');
        });

        $schema->create('approval_request_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['approval_request_id', 'occurred_at'], 'approval_request_events_request_occurred_index');
            $table->index(['event_type', 'occurred_at'], 'approval_request_events_type_occurred_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE approval_workflows ADD CONSTRAINT approval_workflows_area_check CHECK (workflow_area IN ('order_void', 'receipt_void', 'discount_override', 'refund', 'purchase_order', 'stock_adjustment', 'stock_transfer', 'payroll', 'journal_posting', 'supplier_bill', 'expense_claim', 'fiscal_cancellation', 'tax_filing'))");
        $connection->statement('ALTER TABLE approval_workflows ADD CONSTRAINT approval_workflows_amounts_check CHECK ((minimum_amount IS NULL OR minimum_amount >= 0) AND (maximum_amount IS NULL OR maximum_amount >= 0) AND (maximum_amount IS NULL OR minimum_amount IS NULL OR maximum_amount >= minimum_amount))');
        $connection->statement('ALTER TABLE approval_workflow_steps ADD CONSTRAINT approval_workflow_steps_values_check CHECK (step_number > 0 AND required_approval_count > 0)');
        $connection->statement("ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_status_check CHECK (status IN ('pending', 'in_review', 'approved', 'rejected', 'cancelled', 'expired'))");
        $connection->statement('ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_amount_check CHECK (amount IS NULL OR amount >= 0)');
        $connection->statement('ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_dates_check CHECK (completed_at IS NULL OR completed_at >= submitted_at)');
        $connection->statement('ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_source_check CHECK (num_nonnulls(order_id, receipt_id, refund_id, discount_application_id, purchase_order_id, stock_adjustment_id, stock_transfer_id, payroll_run_id, journal_entry_id, supplier_bill_id, expense_claim_id, fiscal_receipt_id, tax_filing_submission_id) = 1)');
        $connection->statement("ALTER TABLE approval_request_steps ADD CONSTRAINT approval_request_steps_status_check CHECK (status IN ('pending', 'open', 'approved', 'rejected', 'skipped', 'cancelled'))");
        $connection->statement('ALTER TABLE approval_request_steps ADD CONSTRAINT approval_request_steps_values_check CHECK (step_number > 0 AND required_approval_count_snapshot > 0)');
        $connection->statement('ALTER TABLE approval_request_steps ADD CONSTRAINT approval_request_steps_dates_check CHECK (completed_at IS NULL OR opened_at IS NULL OR completed_at >= opened_at)');
        $connection->statement("ALTER TABLE approval_decisions ADD CONSTRAINT approval_decisions_decision_check CHECK (decision IN ('approved', 'rejected'))");
        $connection->statement("ALTER TABLE approval_request_events ADD CONSTRAINT approval_request_events_type_check CHECK (event_type IN ('submitted', 'step_opened', 'approved', 'rejected', 'cancelled', 'expired', 'commented', 'reassigned'))");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_order_active_unique ON approval_requests (order_id) WHERE order_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_receipt_active_unique ON approval_requests (receipt_id) WHERE receipt_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_refund_active_unique ON approval_requests (refund_id) WHERE refund_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_discount_active_unique ON approval_requests (discount_application_id) WHERE discount_application_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_purchase_order_active_unique ON approval_requests (purchase_order_id) WHERE purchase_order_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_stock_adjustment_active_unique ON approval_requests (stock_adjustment_id) WHERE stock_adjustment_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_stock_transfer_active_unique ON approval_requests (stock_transfer_id) WHERE stock_transfer_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_payroll_run_active_unique ON approval_requests (payroll_run_id) WHERE payroll_run_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_journal_entry_active_unique ON approval_requests (journal_entry_id) WHERE journal_entry_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_supplier_bill_active_unique ON approval_requests (supplier_bill_id) WHERE supplier_bill_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_expense_claim_active_unique ON approval_requests (expense_claim_id) WHERE expense_claim_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_fiscal_receipt_active_unique ON approval_requests (fiscal_receipt_id) WHERE fiscal_receipt_id IS NOT NULL AND status IN ('pending', 'in_review')");
        $connection->statement("CREATE UNIQUE INDEX approval_requests_tax_filing_active_unique ON approval_requests (tax_filing_submission_id) WHERE tax_filing_submission_id IS NOT NULL AND status IN ('pending', 'in_review')");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS approval_requests_tax_filing_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_fiscal_receipt_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_expense_claim_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_supplier_bill_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_journal_entry_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_payroll_run_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_stock_transfer_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_stock_adjustment_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_purchase_order_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_discount_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_refund_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_receipt_active_unique');
        $connection->statement('DROP INDEX IF EXISTS approval_requests_order_active_unique');

        $schema->dropIfExists('approval_request_events');
        $schema->dropIfExists('approval_decisions');
        $schema->dropIfExists('approval_request_steps');
        $schema->dropIfExists('approval_requests');
        $schema->dropIfExists('approval_workflow_steps');
        $schema->dropIfExists('approval_workflows');
    }
};
