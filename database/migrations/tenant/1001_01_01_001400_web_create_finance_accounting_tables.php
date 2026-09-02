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

        if ($schema->hasTable('journal_entries')) {
            return;
        }

        $schema->create('account_classes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 80);
            $table->string('normal_balance', 8);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['name'], 'account_classes_name_unique');
            $table->index(['is_active', 'name'], 'account_classes_active_name_index');
        });

        $schema->create('account_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('account_class_id')->constrained('account_classes')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('category_nature', 32)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'account_class_id', 'name'], 'account_categories_company_class_name_unique');
            $table->unique(['id', 'company_id'], 'account_categories_id_company_unique');
            $table->index(['company_id', 'account_class_id', 'status'], 'account_categories_company_class_status_index');
            $table->index(['category_nature', 'status'], 'account_categories_nature_status_index');
        });

        $schema->create('account_subcategories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_category_id')->constrained('account_categories')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['account_category_id', 'name'], 'account_subcategories_category_name_unique');
            $table->unique(['id', 'account_category_id'], 'account_subcategories_id_category_unique');
            $table->index(['account_category_id', 'status'], 'account_subcategories_category_status_index');
        });

        $schema->create('chart_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('account_category_id')->constrained('account_categories')->restrictOnDelete();
            $table->foreignId('account_subcategory_id')->nullable()->constrained('account_subcategories')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('account_number', 40)->nullable();
            $table->boolean('is_control_account')->default(false);
            $table->boolean('allow_manual_posting')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'parent_id', 'name'], 'chart_accounts_company_parent_name_unique');
            $table->index(['company_id', 'account_category_id', 'status'], 'chart_accounts_company_category_status_index');
            $table->index(['account_subcategory_id', 'status'], 'chart_accounts_subcategory_status_index');
            $table->index(['parent_id', 'status'], 'chart_accounts_parent_status_index');
        });

        $schema->create('financial_years', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 24)->default('open');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'financial_years_company_name_unique');
            $table->unique(['company_id', 'starts_on', 'ends_on'], 'financial_years_company_dates_unique');
            $table->index(['company_id', 'status', 'starts_on'], 'financial_years_company_status_start_index');
        });

        $schema->create('financial_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->string('name', 120);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 24)->default('open');
            $table->timestampsTz();

            $table->unique(['financial_year_id', 'name'], 'financial_periods_year_name_unique');
            $table->unique(['financial_year_id', 'starts_on', 'ends_on'], 'financial_periods_year_dates_unique');
            $table->index(['financial_year_id', 'status', 'starts_on'], 'financial_periods_year_status_start_index');
        });

        $schema->create('journal_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('source_area', 32);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'journal_sources_company_name_unique');
            $table->index(['company_id', 'source_area', 'status'], 'journal_sources_company_area_status_index');
        });

        $schema->create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('financial_period_id')->nullable()->constrained('financial_periods')->restrictOnDelete();
            $table->foreignId('journal_source_id')->nullable()->constrained('journal_sources')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('provider_settlement_id')->nullable()->constrained('provider_settlements')->nullOnDelete();
            $table->foreignId('created_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('posted_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('journal_number', 50)->unique();
            $table->date('entry_date');
            $table->string('status', 24)->default('draft');
            $table->text('description')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status', 'entry_date'], 'journal_entries_company_status_date_index');
            $table->index(['branch_id', 'status', 'entry_date'], 'journal_entries_branch_status_date_index');
            $table->index(['financial_period_id', 'status'], 'journal_entries_period_status_index');
            $table->index(['journal_source_id', 'entry_date'], 'journal_entries_source_date_index');
            $table->index(['payment_id'], 'journal_entries_payment_index');
            $table->index(['refund_id'], 'journal_entries_refund_index');
            $table->index(['purchase_order_id'], 'journal_entries_purchase_order_index');
            $table->index(['payroll_run_id'], 'journal_entries_payroll_run_index');
            $table->index(['provider_settlement_id'], 'journal_entries_settlement_index');
        });

        $schema->create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->restrictOnDelete();
            $table->decimal('debit_amount', 18, 2)->default(0);
            $table->decimal('credit_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index(['journal_entry_id', 'chart_account_id'], 'journal_lines_entry_account_index');
            $table->index(['chart_account_id'], 'journal_lines_account_index');
            $table->index(['branch_id', 'chart_account_id'], 'journal_lines_branch_account_index');
            $table->index(['cost_centre_id', 'chart_account_id'], 'journal_lines_cost_centre_account_index');
        });

        $schema->create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('bank_name', 120);
            $table->string('account_name', 180);
            $table->string('account_number', 80);
            $table->string('branch_name', 120)->nullable();
            $table->string('currency_code', 3);
            $table->boolean('is_default')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'bank_name', 'account_number'], 'bank_accounts_company_bank_number_unique');
            $table->index(['company_id', 'status'], 'bank_accounts_company_status_index');
            $table->index(['branch_id', 'status'], 'bank_accounts_branch_status_index');
            $table->index(['chart_account_id'], 'bank_accounts_chart_account_index');
        });

        $schema->create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('counter_id')->nullable()->constrained('counters')->restrictOnDelete();
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('account_type', 32)->default('cash_drawer');
            $table->string('currency_code', 3);
            $table->boolean('is_default')->default(false);
            $table->boolean('requires_reconciliation')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'cash_accounts_branch_name_unique');
            $table->index(['branch_id', 'account_type', 'status'], 'cash_accounts_branch_type_status_index');
            $table->index(['counter_id', 'status'], 'cash_accounts_counter_status_index');
            $table->index(['chart_account_id'], 'cash_accounts_chart_account_index');
        });

        $schema->create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_session_id')->nullable()->constrained('shift_sessions')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('expense_claim_id')->nullable();
            $table->foreignId('expense_payment_id')->nullable();
            $table->foreignId('performed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('movement_reason', 40);
            $table->decimal('amount', 18, 2);
            $table->timestampTz('moved_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['cash_account_id', 'moved_at'], 'cash_movements_account_moved_index');
            $table->index(['branch_id', 'movement_reason', 'moved_at'], 'cash_movements_branch_reason_moved_index');
            $table->index(['payment_id'], 'cash_movements_payment_index');
            $table->index(['refund_id'], 'cash_movements_refund_index');
            $table->index(['expense_claim_id'], 'cash_movements_expense_claim_index');
            $table->index(['expense_payment_id'], 'cash_movements_expense_payment_index');
        });

        $schema->create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('expense_type', 32);
            $table->boolean('requires_receipt')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'expense_categories_company_name_unique');
            $table->index(['company_id', 'expense_type', 'status'], 'expense_categories_company_type_status_index');
            $table->index(['chart_account_id', 'status'], 'expense_categories_account_status_index');
        });

        $schema->create('expense_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('expense_number', 50)->unique();
            $table->date('expense_date');
            $table->string('description', 180);
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('draft');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status', 'expense_date'], 'expense_claims_branch_status_date_index');
            $table->index(['expense_category_id', 'status'], 'expense_claims_category_status_index');
            $table->index(['requested_by_tenant_user_id', 'status'], 'expense_claims_requester_status_index');
            $table->index(['supplier_id', 'status'], 'expense_claims_supplier_status_index');
            $table->index(['journal_entry_id'], 'expense_claims_journal_entry_index');
        });

        $schema->create('expense_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('expense_claim_id')->constrained('expense_claims')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('paid_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3);
            $table->string('payment_reference', 120)->nullable();
            $table->string('status', 24)->default('completed');
            $table->timestampTz('paid_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['expense_claim_id', 'status'], 'expense_payments_claim_status_index');
            $table->index(['branch_id', 'status', 'paid_at'], 'expense_payments_branch_status_paid_index');
            $table->index(['cash_account_id', 'paid_at'], 'expense_payments_cash_paid_index');
            $table->index(['bank_account_id', 'paid_at'], 'expense_payments_bank_paid_index');
        });

        $schema->create('supplier_bills', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('bill_number', 80);
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('draft');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['supplier_id', 'bill_number'], 'supplier_bills_supplier_number_unique');
            $table->index(['branch_id', 'status', 'bill_date'], 'supplier_bills_branch_status_date_index');
            $table->index(['supplier_id', 'status', 'due_date'], 'supplier_bills_supplier_status_due_index');
            $table->index(['purchase_order_id'], 'supplier_bills_purchase_order_index');
        });

        $schema->create('supplier_bill_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('description', 180);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestampsTz();

            $table->index(['supplier_bill_id', 'sort_order'], 'supplier_bill_items_bill_sort_index');
            $table->index(['ingredient_id'], 'supplier_bill_items_ingredient_index');
            $table->index(['chart_account_id'], 'supplier_bill_items_account_index');
        });

        $schema->create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('paid_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3);
            $table->string('payment_reference', 120)->nullable();
            $table->string('status', 24)->default('completed');
            $table->timestampTz('paid_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['supplier_bill_id', 'status'], 'supplier_payments_bill_status_index');
            $table->index(['branch_id', 'status', 'paid_at'], 'supplier_payments_branch_status_paid_index');
            $table->index(['bank_account_id', 'paid_at'], 'supplier_payments_bank_paid_index');
            $table->index(['cash_account_id', 'paid_at'], 'supplier_payments_cash_paid_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE account_classes ADD CONSTRAINT account_classes_normal_balance_check CHECK (normal_balance IN ('debit', 'credit'))");
        $connection->statement("ALTER TABLE account_categories ADD CONSTRAINT account_categories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE account_categories ADD CONSTRAINT account_categories_nature_check CHECK (category_nature IS NULL OR category_nature IN ('current', 'non_current', 'direct', 'indirect', 'operating', 'non_operating', 'statutory'))");
        $connection->statement("ALTER TABLE account_subcategories ADD CONSTRAINT account_subcategories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE chart_accounts ADD CONSTRAINT chart_accounts_status_check CHECK (status IN ('active', 'inactive', 'archived'))");
        $connection->statement('ALTER TABLE chart_accounts ADD CONSTRAINT chart_accounts_category_company_fk FOREIGN KEY (account_category_id, company_id) REFERENCES account_categories (id, company_id)');
        $connection->statement('ALTER TABLE chart_accounts ADD CONSTRAINT chart_accounts_subcategory_category_fk FOREIGN KEY (account_subcategory_id, account_category_id) REFERENCES account_subcategories (id, account_category_id)');
        $connection->statement("CREATE UNIQUE INDEX chart_accounts_company_number_unique ON chart_accounts (company_id, account_number) WHERE account_number IS NOT NULL");
        $connection->statement("ALTER TABLE financial_years ADD CONSTRAINT financial_years_status_check CHECK (status IN ('open', 'closed', 'locked'))");
        $connection->statement('ALTER TABLE financial_years ADD CONSTRAINT financial_years_dates_check CHECK (ends_on >= starts_on)');
        $connection->statement("CREATE UNIQUE INDEX financial_years_one_open_unique ON financial_years (company_id) WHERE status = 'open'");
        $connection->statement("ALTER TABLE financial_periods ADD CONSTRAINT financial_periods_status_check CHECK (status IN ('open', 'closed', 'locked'))");
        $connection->statement('ALTER TABLE financial_periods ADD CONSTRAINT financial_periods_dates_check CHECK (ends_on >= starts_on)');
        $connection->statement("ALTER TABLE journal_sources ADD CONSTRAINT journal_sources_area_check CHECK (source_area IN ('manual', 'sales', 'purchases', 'inventory', 'payroll', 'payments', 'banking', 'settlement'))");
        $connection->statement("ALTER TABLE journal_sources ADD CONSTRAINT journal_sources_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_status_check CHECK (status IN ('draft', 'posted', 'reversed', 'cancelled'))");
        $connection->statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_posted_check CHECK ((status = \'posted\' AND posted_at IS NOT NULL) OR status <> \'posted\')');
        $connection->statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_source_check CHECK (num_nonnulls(payment_id, refund_id, purchase_order_id, payroll_run_id, provider_settlement_id) <= 1)');
        $connection->statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT journal_entry_lines_amount_check CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0))');
        $connection->statement("ALTER TABLE bank_accounts ADD CONSTRAINT bank_accounts_status_check CHECK (status IN ('active', 'inactive', 'closed'))");
        $connection->statement("CREATE UNIQUE INDEX bank_accounts_company_default_unique ON bank_accounts (company_id, currency_code) WHERE is_default = true AND branch_id IS NULL AND status = 'active'");
        $connection->statement("CREATE UNIQUE INDEX bank_accounts_branch_default_unique ON bank_accounts (branch_id, currency_code) WHERE is_default = true AND branch_id IS NOT NULL AND status = 'active'");
        $connection->statement("ALTER TABLE cash_accounts ADD CONSTRAINT cash_accounts_type_check CHECK (account_type IN ('cash_drawer', 'petty_cash', 'safe', 'cash_in_transit'))");
        $connection->statement("ALTER TABLE cash_accounts ADD CONSTRAINT cash_accounts_status_check CHECK (status IN ('active', 'inactive', 'closed'))");
        $connection->statement("CREATE UNIQUE INDEX cash_accounts_branch_default_unique ON cash_accounts (branch_id, currency_code) WHERE is_default = true AND status = 'active'");
        $connection->statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_reason_check CHECK (movement_reason IN ('opening_float', 'cash_sale', 'cash_refund', 'cash_in', 'cash_out', 'safe_drop', 'supplier_payment', 'expense_payment', 'petty_cash_top_up', 'petty_cash_return', 'closing_variance'))");
        $connection->statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_check CHECK (amount <> 0)');
        $connection->statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_expense_claim_fk FOREIGN KEY (expense_claim_id) REFERENCES expense_claims (id) ON DELETE SET NULL');
        $connection->statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_expense_payment_fk FOREIGN KEY (expense_payment_id) REFERENCES expense_payments (id) ON DELETE SET NULL');
        $connection->statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_source_check CHECK (num_nonnulls(payment_id, refund_id, journal_entry_id, expense_claim_id, expense_payment_id) <= 1)');
        $connection->statement("ALTER TABLE expense_categories ADD CONSTRAINT expense_categories_type_check CHECK (expense_type IN ('operating', 'admin', 'staff', 'repairs', 'utilities', 'transport', 'marketing', 'other'))");
        $connection->statement("ALTER TABLE expense_categories ADD CONSTRAINT expense_categories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE expense_claims ADD CONSTRAINT expense_claims_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'rejected', 'partially_paid', 'paid', 'voided'))");
        $connection->statement('ALTER TABLE expense_claims ADD CONSTRAINT expense_claims_amounts_check CHECK (subtotal_amount >= 0 AND tax_amount >= 0 AND total_amount > 0 AND paid_amount >= 0 AND paid_amount <= total_amount)');
        $connection->statement('ALTER TABLE expense_claims ADD CONSTRAINT expense_claims_dates_check CHECK ((approved_at IS NULL OR submitted_at IS NULL OR approved_at >= submitted_at) AND (rejected_at IS NULL OR submitted_at IS NULL OR rejected_at >= submitted_at))');
        $connection->statement("ALTER TABLE expense_payments ADD CONSTRAINT expense_payments_status_check CHECK (status IN ('completed', 'voided', 'reversed'))");
        $connection->statement('ALTER TABLE expense_payments ADD CONSTRAINT expense_payments_amount_check CHECK (amount > 0)');
        $connection->statement('ALTER TABLE expense_payments ADD CONSTRAINT expense_payments_source_check CHECK (num_nonnulls(cash_account_id, bank_account_id) = 1)');
        $connection->statement("ALTER TABLE supplier_bills ADD CONSTRAINT supplier_bills_status_check CHECK (status IN ('draft', 'approved', 'partially_paid', 'paid', 'voided'))");
        $connection->statement('ALTER TABLE supplier_bills ADD CONSTRAINT supplier_bills_amounts_check CHECK (subtotal_amount >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0)');
        $connection->statement('ALTER TABLE supplier_bills ADD CONSTRAINT supplier_bills_dates_check CHECK (due_date IS NULL OR due_date >= bill_date)');
        $connection->statement('ALTER TABLE supplier_bill_items ADD CONSTRAINT supplier_bill_items_amounts_check CHECK (quantity > 0 AND unit_price >= 0 AND tax_amount >= 0 AND line_total >= 0 AND sort_order > 0)');
        $connection->statement("ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_status_check CHECK (status IN ('completed', 'voided', 'reversed'))");
        $connection->statement('ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_amount_check CHECK (amount > 0)');
        $connection->statement('ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_source_check CHECK (num_nonnulls(bank_account_id, cash_account_id) = 1)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS cash_accounts_branch_default_unique');
        $connection->statement('DROP INDEX IF EXISTS bank_accounts_branch_default_unique');
        $connection->statement('DROP INDEX IF EXISTS bank_accounts_company_default_unique');
        $connection->statement('DROP INDEX IF EXISTS financial_years_one_open_unique');
        $connection->statement('DROP INDEX IF EXISTS chart_accounts_company_number_unique');

        $schema->dropIfExists('supplier_payments');
        $schema->dropIfExists('supplier_bill_items');
        $schema->dropIfExists('supplier_bills');
        $schema->dropIfExists('cash_movements');
        $schema->dropIfExists('expense_payments');
        $schema->dropIfExists('expense_claims');
        $schema->dropIfExists('expense_categories');
        $schema->dropIfExists('cash_accounts');
        $schema->dropIfExists('bank_accounts');
        $schema->dropIfExists('journal_entry_lines');
        $schema->dropIfExists('journal_entries');
        $schema->dropIfExists('journal_sources');
        $schema->dropIfExists('financial_periods');
        $schema->dropIfExists('financial_years');
        $schema->dropIfExists('chart_accounts');
        $schema->dropIfExists('account_subcategories');
        $schema->dropIfExists('account_categories');
        $schema->dropIfExists('account_classes');
    }
};
