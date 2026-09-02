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

        if ($schema->hasTable('business_documents')) {
            return;
        }

        $schema->create('document_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('document_area', 40);
            $table->boolean('requires_expiry_date')->default(false);
            $table->boolean('requires_review')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'document_categories_company_name_unique');
            $table->index(['company_id', 'document_area', 'status'], 'document_categories_company_area_status_index');
        });

        $schema->create('document_requirements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('document_category_id')->constrained('document_categories')->restrictOnDelete();
            $table->string('required_for', 40);
            $table->string('name', 140);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('requires_expiry_date')->default(false);
            $table->boolean('requires_review')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'required_for', 'name'], 'document_requirements_scope_name_unique');
            $table->index(['company_id', 'required_for', 'status'], 'document_requirements_company_required_status_index');
            $table->index(['branch_id', 'required_for', 'status'], 'document_requirements_branch_required_status_index');
            $table->index(['document_category_id', 'status'], 'document_requirements_category_status_index');
        });

        $schema->create('business_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('document_category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->foreignId('document_requirement_id')->nullable()->constrained('document_requirements')->nullOnDelete();
            $table->foreignId('uploaded_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('payroll_run_employee_id')->nullable()->constrained('payroll_run_employees')->nullOnDelete();
            $table->foreignId('fiscal_receipt_id')->nullable()->constrained('fiscal_receipts')->nullOnDelete();
            $table->foreignId('tax_filing_submission_id')->nullable()->constrained('tax_filing_submissions')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('title', 180);
            $table->string('document_number', 120)->nullable();
            $table->date('document_date')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 24)->default('draft');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status', 'document_date'], 'business_documents_company_status_date_index');
            $table->index(['branch_id', 'status', 'document_date'], 'business_documents_branch_status_date_index');
            $table->index(['document_category_id', 'status'], 'business_documents_category_status_index');
            $table->index(['document_requirement_id', 'status'], 'business_documents_requirement_status_index');
            $table->index(['tenant_user_id', 'status'], 'business_documents_tenant_user_status_index');
            $table->index(['supplier_id', 'status'], 'business_documents_supplier_status_index');
            $table->index(['customer_id', 'status'], 'business_documents_customer_status_index');
            $table->index(['purchase_order_id'], 'business_documents_purchase_order_index');
            $table->index(['goods_receipt_id'], 'business_documents_goods_receipt_index');
            $table->index(['supplier_bill_id'], 'business_documents_supplier_bill_index');
            $table->index(['payment_id'], 'business_documents_payment_index');
            $table->index(['refund_id'], 'business_documents_refund_index');
            $table->index(['payroll_run_id'], 'business_documents_payroll_run_index');
            $table->index(['payroll_run_employee_id'], 'business_documents_payroll_employee_index');
            $table->index(['fiscal_receipt_id'], 'business_documents_fiscal_receipt_index');
            $table->index(['tax_filing_submission_id'], 'business_documents_tax_filing_index');
            $table->index(['journal_entry_id'], 'business_documents_journal_entry_index');
        });

        $schema->create('business_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->foreignId('uploaded_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_name_snapshot', 255);
            $table->string('mime_type_snapshot', 120)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('status', 24)->default('active');
            $table->text('change_notes')->nullable();
            $table->timestampsTz();

            $table->unique(['business_document_id', 'version_number'], 'business_document_versions_document_version_unique');
            $table->index(['business_document_id', 'is_current'], 'business_document_versions_document_current_index');
            $table->index(['checksum_sha256'], 'business_document_versions_checksum_index');
        });

        $schema->create('business_document_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->foreignId('business_document_version_id')->nullable()->constrained('business_document_versions')->nullOnDelete();
            $table->foreignId('reviewed_by_tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->string('decision', 24);
            $table->text('comments')->nullable();
            $table->timestampTz('reviewed_at');
            $table->timestampsTz();

            $table->index(['business_document_id', 'reviewed_at'], 'business_document_reviews_document_reviewed_index');
            $table->index(['reviewed_by_tenant_user_id', 'reviewed_at'], 'business_document_reviews_reviewer_reviewed_index');
        });

        $schema->create('business_document_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['business_document_id', 'occurred_at'], 'business_document_events_document_occurred_index');
            $table->index(['event_type', 'occurred_at'], 'business_document_events_type_occurred_index');
        });

        $schema->create('data_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('business_document_id')->nullable()->constrained('business_documents')->nullOnDelete();
            $table->foreignId('uploaded_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('import_area', 40);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('summary')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'import_area', 'status'], 'data_import_batches_company_area_status_index');
            $table->index(['branch_id', 'import_area', 'status'], 'data_import_batches_branch_area_status_index');
            $table->index(['business_document_id'], 'data_import_batches_document_index');
        });

        $schema->create('data_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('data_import_batch_id')->constrained('data_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field_name', 120)->nullable();
            $table->text('error_message');
            $table->jsonb('row_payload')->nullable();
            $table->timestampsTz();

            $table->index(['data_import_batch_id', 'row_number'], 'data_import_errors_batch_row_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE document_categories ADD CONSTRAINT document_categories_area_check CHECK (document_area IN ('company', 'branch', 'staff', 'supplier', 'customer', 'procurement', 'finance', 'payroll', 'tax', 'fiscal', 'inventory', 'menu', 'import'))");
        $connection->statement("ALTER TABLE document_categories ADD CONSTRAINT document_categories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE document_requirements ADD CONSTRAINT document_requirements_required_for_check CHECK (required_for IN ('company', 'branch', 'staff', 'supplier', 'customer', 'purchase_order', 'goods_receipt', 'supplier_bill', 'payment', 'refund', 'payroll', 'tax_filing', 'fiscal_receipt', 'journal_entry'))");
        $connection->statement("ALTER TABLE document_requirements ADD CONSTRAINT document_requirements_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE business_documents ADD CONSTRAINT business_documents_status_check CHECK (status IN ('draft', 'pending_review', 'approved', 'rejected', 'expired', 'archived'))");
        $connection->statement('ALTER TABLE business_documents ADD CONSTRAINT business_documents_dates_check CHECK (expires_on IS NULL OR document_date IS NULL OR expires_on >= document_date)');
        $connection->statement('ALTER TABLE business_documents ADD CONSTRAINT business_documents_owner_check CHECK (num_nonnulls(tenant_user_id, supplier_id, customer_id, purchase_order_id, goods_receipt_id, supplier_bill_id, payment_id, refund_id, payroll_run_id, payroll_run_employee_id, fiscal_receipt_id, tax_filing_submission_id, journal_entry_id) >= 1)');
        $connection->statement("CREATE UNIQUE INDEX business_documents_number_scope_unique ON business_documents (company_id, document_category_id, document_number) WHERE document_number IS NOT NULL");
        $connection->statement("ALTER TABLE business_document_versions ADD CONSTRAINT business_document_versions_status_check CHECK (status IN ('active', 'superseded', 'removed'))");
        $connection->statement('ALTER TABLE business_document_versions ADD CONSTRAINT business_document_versions_values_check CHECK (version_number > 0 AND (file_size_bytes IS NULL OR file_size_bytes > 0))');
        $connection->statement("CREATE UNIQUE INDEX business_document_versions_one_current_unique ON business_document_versions (business_document_id) WHERE is_current = true AND status = 'active'");
        $connection->statement("ALTER TABLE business_document_reviews ADD CONSTRAINT business_document_reviews_decision_check CHECK (decision IN ('approved', 'rejected', 'needs_changes'))");
        $connection->statement("ALTER TABLE business_document_events ADD CONSTRAINT business_document_events_type_check CHECK (event_type IN ('created', 'uploaded', 'submitted_for_review', 'approved', 'rejected', 'expired', 'archived', 'version_added', 'downloaded'))");
        $connection->statement("ALTER TABLE data_import_batches ADD CONSTRAINT data_import_batches_area_check CHECK (import_area IN ('menu', 'ingredients', 'suppliers', 'customers', 'staff', 'stock_opening', 'prices', 'chart_accounts'))");
        $connection->statement("ALTER TABLE data_import_batches ADD CONSTRAINT data_import_batches_status_check CHECK (status IN ('pending', 'processing', 'completed', 'completed_with_errors', 'failed', 'cancelled'))");
        $connection->statement('ALTER TABLE data_import_batches ADD CONSTRAINT data_import_batches_counts_check CHECK (total_rows >= 0 AND successful_rows >= 0 AND failed_rows >= 0 AND successful_rows + failed_rows <= total_rows)');
        $connection->statement('ALTER TABLE data_import_batches ADD CONSTRAINT data_import_batches_dates_check CHECK (completed_at IS NULL OR started_at IS NULL OR completed_at >= started_at)');
        $connection->statement('ALTER TABLE data_import_errors ADD CONSTRAINT data_import_errors_row_check CHECK (row_number > 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS business_document_versions_one_current_unique');
        $connection->statement('DROP INDEX IF EXISTS business_documents_number_scope_unique');

        $schema->dropIfExists('data_import_errors');
        $schema->dropIfExists('data_import_batches');
        $schema->dropIfExists('business_document_events');
        $schema->dropIfExists('business_document_reviews');
        $schema->dropIfExists('business_document_versions');
        $schema->dropIfExists('business_documents');
        $schema->dropIfExists('document_requirements');
        $schema->dropIfExists('document_categories');
    }
};
