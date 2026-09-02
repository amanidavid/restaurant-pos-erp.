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

        if ($schema->hasTable('fiscal_receipts')) {
            return;
        }

        $schema->create('tax_authorities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('country_code', 2);
            $table->string('authority_type', 32);
            $table->string('status', 24)->default('active');
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'tax_authorities_company_name_unique');
            $table->index(['company_id', 'authority_type', 'status'], 'tax_authorities_company_type_status_index');
        });

        $schema->create('tax_registrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('tax_authority_id')->constrained('tax_authorities')->restrictOnDelete();
            $table->string('registration_type', 32);
            $table->string('registration_number', 80);
            $table->date('registered_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(
                ['tax_authority_id', 'registration_type', 'registration_number'],
                'tax_registrations_authority_type_number_unique'
            );
            $table->index(['company_id', 'registration_type', 'status'], 'tax_registrations_company_type_status_index');
            $table->index(['branch_id', 'registration_type', 'status'], 'tax_registrations_branch_type_status_index');
        });

        $schema->create('tax_code_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->cascadeOnDelete();
            $table->foreignId('output_tax_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->foreignId('input_tax_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->foreignId('tax_expense_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['tax_code_id'], 'tax_code_accounts_tax_code_unique');
            $table->index(['output_tax_account_id'], 'tax_code_accounts_output_account_index');
            $table->index(['input_tax_account_id'], 'tax_code_accounts_input_account_index');
            $table->index(['tax_expense_account_id'], 'tax_code_accounts_expense_account_index');
        });

        $schema->create('tax_exemption_reasons', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'tax_exemption_reasons_company_name_unique');
            $table->index(['company_id', 'status'], 'tax_exemption_reasons_company_status_index');
        });

        $schema->create('tax_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();
            $table->foreignId('tax_exemption_reason_id')->nullable()->constrained('tax_exemption_reasons')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->foreignId('supplier_bill_item_id')->nullable()->constrained('supplier_bill_items')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('transaction_direction', 16);
            $table->date('tax_date');
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('posted');
            $table->timestampsTz();

            $table->index(['company_id', 'transaction_direction', 'tax_date'], 'tax_transactions_company_direction_date_index');
            $table->index(['branch_id', 'transaction_direction', 'tax_date'], 'tax_transactions_branch_direction_date_index');
            $table->index(['tax_code_id', 'tax_date'], 'tax_transactions_tax_code_date_index');
            $table->index(['order_id'], 'tax_transactions_order_index');
            $table->index(['receipt_id'], 'tax_transactions_receipt_index');
            $table->index(['supplier_bill_id'], 'tax_transactions_supplier_bill_index');
            $table->index(['journal_entry_id'], 'tax_transactions_journal_entry_index');
        });

        $schema->create('fiscal_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('tax_authority_id')->nullable()->constrained('tax_authorities')->restrictOnDelete();
            $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->nullOnDelete();
            $table->foreignId('print_device_id')->nullable()->constrained('print_devices')->nullOnDelete();
            $table->string('name', 120);
            $table->string('device_type', 32);
            $table->string('serial_number', 120)->nullable();
            $table->string('registration_number', 120)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 24)->default('active');
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'fiscal_devices_branch_name_unique');
            $table->index(['branch_id', 'device_type', 'status'], 'fiscal_devices_branch_type_status_index');
            $table->index(['integration_account_id', 'status'], 'fiscal_devices_account_status_index');
        });

        $schema->create('fiscal_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('fiscal_device_id')->nullable()->constrained('fiscal_devices')->nullOnDelete();
            $table->foreignId('tax_registration_id')->nullable()->constrained('tax_registrations')->nullOnDelete();
            $table->foreignId('submitted_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('document_type', 32);
            $table->string('fiscal_number', 120)->nullable();
            $table->string('verification_code', 160)->nullable();
            $table->string('qr_url', 500)->nullable();
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('pending');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status', 'created_at'], 'fiscal_receipts_branch_status_created_index');
            $table->index(['receipt_id'], 'fiscal_receipts_receipt_index');
            $table->index(['refund_id'], 'fiscal_receipts_refund_index');
            $table->index(['fiscal_device_id', 'status'], 'fiscal_receipts_device_status_index');
            $table->index(['tax_registration_id', 'status'], 'fiscal_receipts_registration_status_index');
        });

        $schema->create('fiscal_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fiscal_receipt_id')->constrained('fiscal_receipts')->cascadeOnDelete();
            $table->foreignId('tax_transaction_id')->nullable()->constrained('tax_transactions')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('description', 180);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestampsTz();

            $table->index(['fiscal_receipt_id'], 'fiscal_receipt_lines_receipt_index');
            $table->index(['tax_transaction_id'], 'fiscal_receipt_lines_tax_transaction_index');
            $table->index(['tax_code_id'], 'fiscal_receipt_lines_tax_code_index');
        });

        $schema->create('tax_return_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('tax_authority_id')->constrained('tax_authorities')->restrictOnDelete();
            $table->string('return_type', 32);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'tax_authority_id', 'return_type', 'period_start', 'period_end'],
                'tax_return_periods_scope_dates_unique'
            );
            $table->index(['company_id', 'return_type', 'status', 'period_start'], 'tax_return_periods_company_type_status_start_index');
            $table->index(['tax_authority_id', 'status', 'due_date'], 'tax_return_periods_authority_status_due_index');
        });

        $schema->create('tax_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tax_return_period_id')->constrained('tax_return_periods')->cascadeOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('line_type', 32);
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['tax_return_period_id', 'line_type'], 'tax_return_lines_period_type_index');
            $table->index(['tax_code_id'], 'tax_return_lines_tax_code_index');
        });

        $schema->create('tax_filing_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tax_return_period_id')->constrained('tax_return_periods')->restrictOnDelete();
            $table->foreignId('integration_account_id')->nullable()->constrained('integration_accounts')->nullOnDelete();
            $table->foreignId('submitted_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('submission_reference', 160)->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->text('response_message')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->timestampsTz();

            $table->index(['tax_return_period_id', 'status'], 'tax_filing_submissions_period_status_index');
            $table->index(['integration_account_id', 'status'], 'tax_filing_submissions_account_status_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE tax_authorities ADD CONSTRAINT tax_authorities_type_check CHECK (authority_type IN ('national_tax', 'local_tax', 'fiscal_receipt', 'statutory'))");
        $connection->statement("ALTER TABLE tax_authorities ADD CONSTRAINT tax_authorities_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE tax_registrations ADD CONSTRAINT tax_registrations_type_check CHECK (registration_type IN ('tin', 'vrn', 'vat', 'fiscal_device', 'local_levy'))");
        $connection->statement("ALTER TABLE tax_registrations ADD CONSTRAINT tax_registrations_status_check CHECK (status IN ('active', 'inactive', 'expired'))");
        $connection->statement('ALTER TABLE tax_registrations ADD CONSTRAINT tax_registrations_dates_check CHECK (expires_on IS NULL OR registered_on IS NULL OR expires_on >= registered_on)');
        $connection->statement("ALTER TABLE tax_exemption_reasons ADD CONSTRAINT tax_exemption_reasons_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE tax_transactions ADD CONSTRAINT tax_transactions_direction_check CHECK (transaction_direction IN ('output', 'input', 'adjustment'))");
        $connection->statement("ALTER TABLE tax_transactions ADD CONSTRAINT tax_transactions_status_check CHECK (status IN ('draft', 'posted', 'reversed'))");
        $connection->statement('ALTER TABLE tax_transactions ADD CONSTRAINT tax_transactions_amounts_check CHECK (taxable_amount >= 0 AND tax_amount >= 0)');
        $connection->statement('ALTER TABLE tax_transactions ADD CONSTRAINT tax_transactions_source_check CHECK (num_nonnulls(order_id, order_item_id, receipt_id, refund_id, supplier_bill_id, supplier_bill_item_id, journal_entry_id) >= 1)');
        $connection->statement("ALTER TABLE fiscal_devices ADD CONSTRAINT fiscal_devices_type_check CHECK (device_type IN ('efd', 'vfd', 'api', 'manual'))");
        $connection->statement("ALTER TABLE fiscal_devices ADD CONSTRAINT fiscal_devices_status_check CHECK (status IN ('active', 'inactive', 'maintenance', 'retired'))");
        $connection->statement("CREATE UNIQUE INDEX fiscal_devices_branch_default_unique ON fiscal_devices (branch_id) WHERE is_default = true AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX fiscal_devices_serial_unique ON fiscal_devices (serial_number) WHERE serial_number IS NOT NULL");
        $connection->statement("ALTER TABLE fiscal_receipts ADD CONSTRAINT fiscal_receipts_document_type_check CHECK (document_type IN ('sale_receipt', 'refund_receipt', 'credit_note', 'debit_note'))");
        $connection->statement("ALTER TABLE fiscal_receipts ADD CONSTRAINT fiscal_receipts_status_check CHECK (status IN ('pending', 'submitted', 'accepted', 'rejected', 'cancelled'))");
        $connection->statement('ALTER TABLE fiscal_receipts ADD CONSTRAINT fiscal_receipts_amounts_check CHECK (taxable_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0)');
        $connection->statement('ALTER TABLE fiscal_receipts ADD CONSTRAINT fiscal_receipts_source_check CHECK (num_nonnulls(receipt_id, refund_id) = 1)');
        $connection->statement("CREATE UNIQUE INDEX fiscal_receipts_fiscal_number_unique ON fiscal_receipts (fiscal_device_id, fiscal_number) WHERE fiscal_device_id IS NOT NULL AND fiscal_number IS NOT NULL");
        $connection->statement('ALTER TABLE fiscal_receipt_lines ADD CONSTRAINT fiscal_receipt_lines_amounts_check CHECK (quantity > 0 AND unit_price >= 0 AND taxable_amount >= 0 AND tax_amount >= 0 AND line_total >= 0)');
        $connection->statement("ALTER TABLE tax_return_periods ADD CONSTRAINT tax_return_periods_type_check CHECK (return_type IN ('vat', 'sales_tax', 'withholding', 'local_levy'))");
        $connection->statement("ALTER TABLE tax_return_periods ADD CONSTRAINT tax_return_periods_status_check CHECK (status IN ('open', 'prepared', 'submitted', 'accepted', 'closed', 'reopened'))");
        $connection->statement('ALTER TABLE tax_return_periods ADD CONSTRAINT tax_return_periods_dates_check CHECK (period_end >= period_start AND (due_date IS NULL OR due_date >= period_start))');
        $connection->statement("ALTER TABLE tax_return_lines ADD CONSTRAINT tax_return_lines_type_check CHECK (line_type IN ('output_tax', 'input_tax', 'exempt_sales', 'zero_rated_sales', 'adjustment', 'payable', 'recoverable'))");
        $connection->statement('ALTER TABLE tax_return_lines ADD CONSTRAINT tax_return_lines_amounts_check CHECK (taxable_amount >= 0 AND tax_amount >= 0)');
        $connection->statement("ALTER TABLE tax_filing_submissions ADD CONSTRAINT tax_filing_submissions_status_check CHECK (status IN ('draft', 'submitted', 'accepted', 'rejected', 'cancelled'))");
        $connection->statement("CREATE UNIQUE INDEX tax_filing_submissions_reference_unique ON tax_filing_submissions (submission_reference) WHERE submission_reference IS NOT NULL");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS tax_filing_submissions_reference_unique');
        $connection->statement('DROP INDEX IF EXISTS fiscal_receipts_fiscal_number_unique');
        $connection->statement('DROP INDEX IF EXISTS fiscal_devices_serial_unique');
        $connection->statement('DROP INDEX IF EXISTS fiscal_devices_branch_default_unique');

        $schema->dropIfExists('tax_filing_submissions');
        $schema->dropIfExists('tax_return_lines');
        $schema->dropIfExists('tax_return_periods');
        $schema->dropIfExists('fiscal_receipt_lines');
        $schema->dropIfExists('fiscal_receipts');
        $schema->dropIfExists('fiscal_devices');
        $schema->dropIfExists('tax_transactions');
        $schema->dropIfExists('tax_exemption_reasons');
        $schema->dropIfExists('tax_code_accounts');
        $schema->dropIfExists('tax_registrations');
        $schema->dropIfExists('tax_authorities');
    }
};
