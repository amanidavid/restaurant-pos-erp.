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

        if ($schema->hasTable('payroll_periods')) {
            return;
        }

        $schema->create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestampsTz();

            $table->unique(['company_id', 'period_start', 'period_end'], 'payroll_periods_company_period_unique');
            $table->index(['company_id', 'status'], 'payroll_periods_company_status_index');
        });

        $schema->create('earning_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('calculation_type', 24)->default('fixed');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pensionable')->default(true);
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'earning_types_company_name_unique');
        });

        $schema->create('deduction_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('calculation_type', 24)->default('fixed');
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'deduction_types_company_name_unique');
        });

        $schema->create('employee_earnings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('earning_type_id')->constrained('earning_types')->restrictOnDelete();
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('rate', 10, 4)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'status'], 'employee_earnings_user_status_index');
        });

        $schema->create('employee_deductions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->restrictOnDelete();
            $table->decimal('amount', 18, 2)->nullable();
            $table->decimal('rate', 10, 4)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'status'], 'employee_deductions_user_status_index');
        });

        $schema->create('statutory_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('authority_name', 120)->nullable();
            $table->string('rule_type', 24);
            $table->boolean('employee_contributes')->default(true);
            $table->boolean('employer_contributes')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name', 'effective_from'], 'statutory_rules_company_name_effective_unique');
            $table->index(['company_id', 'status'], 'statutory_rules_company_status_index');
        });

        $schema->create('statutory_brackets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('statutory_rule_id')->constrained('statutory_rules')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('min_amount', 18, 2)->nullable();
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->decimal('employee_rate', 10, 4)->nullable();
            $table->decimal('employer_rate', 10, 4)->nullable();
            $table->decimal('fixed_amount', 18, 2)->nullable();
            $table->timestampsTz();

            $table->unique(['statutory_rule_id', 'sequence'], 'statutory_brackets_rule_sequence_unique');
        });

        $schema->create('employee_statutory_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('statutory_rule_id')->constrained('statutory_rules')->restrictOnDelete();
            $table->string('reference_number', 80)->nullable();
            $table->boolean('is_exempt')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['tenant_user_id', 'statutory_rule_id'], 'employee_statutory_profiles_user_rule_unique');
        });

        $schema->create('loan_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('default_interest_rate', 10, 4)->default(0);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'loan_types_company_name_unique');
        });

        $schema->create('employee_loans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('loan_type_id')->constrained('loan_types')->restrictOnDelete();
            $table->string('loan_number', 40);
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('interest_rate', 10, 4)->default(0);
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->decimal('installment_amount', 18, 2)->nullable();
            $table->date('issued_on');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['tenant_user_id', 'loan_number'], 'employee_loans_user_number_unique');
            $table->index(['tenant_user_id', 'status'], 'employee_loans_user_status_index');
        });

        $schema->create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('processed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('run_number', 40);
            $table->string('status', 24)->default('draft');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status'], 'payroll_runs_company_status_index');
            $table->index(['branch_id', 'status'], 'payroll_runs_branch_status_index');
            $table->index(['payroll_period_id', 'status'], 'payroll_runs_period_status_index');
        });

        $schema->create('payroll_run_employees', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('deduction_amount', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->string('status', 24)->default('draft');
            $table->timestampsTz();

            $table->unique(['payroll_run_id', 'tenant_user_id'], 'payroll_run_employees_run_user_unique');
            $table->index(['tenant_user_id', 'status'], 'payroll_run_employees_user_status_index');
        });

        $schema->create('loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->nullable()->constrained('payroll_run_employees')->nullOnDelete();
            $table->date('repayment_date');
            $table->decimal('amount', 18, 2);
            $table->string('status', 24)->default('posted');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['employee_loan_id', 'repayment_date'], 'loan_repayments_loan_date_index');
        });

        $schema->create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees')->cascadeOnDelete();
            $table->string('payslip_number', 40)->unique();
            $table->timestampTz('generated_at');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_status_check CHECK (status IN ('open', 'processing', 'closed'))");
        $connection->statement('ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_dates_check CHECK (period_end >= period_start AND (pay_date IS NULL OR pay_date >= period_start))');
        $connection->statement("ALTER TABLE earning_types ADD CONSTRAINT earning_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE earning_types ADD CONSTRAINT earning_types_calculation_type_check CHECK (calculation_type IN ('fixed', 'rate', 'formula'))");
        $connection->statement("ALTER TABLE deduction_types ADD CONSTRAINT deduction_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE deduction_types ADD CONSTRAINT deduction_types_calculation_type_check CHECK (calculation_type IN ('fixed', 'rate', 'formula'))");
        $connection->statement("ALTER TABLE employee_earnings ADD CONSTRAINT employee_earnings_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE employee_earnings ADD CONSTRAINT employee_earnings_values_check CHECK ((amount IS NOT NULL OR rate IS NOT NULL) AND (amount IS NULL OR amount >= 0) AND (rate IS NULL OR rate >= 0) AND (quantity IS NULL OR quantity > 0))');
        $connection->statement('ALTER TABLE employee_earnings ADD CONSTRAINT employee_earnings_dates_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        $connection->statement("ALTER TABLE employee_deductions ADD CONSTRAINT employee_deductions_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE employee_deductions ADD CONSTRAINT employee_deductions_values_check CHECK ((amount IS NOT NULL OR rate IS NOT NULL) AND (amount IS NULL OR amount >= 0) AND (rate IS NULL OR rate >= 0))');
        $connection->statement('ALTER TABLE employee_deductions ADD CONSTRAINT employee_deductions_dates_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        $connection->statement("ALTER TABLE statutory_rules ADD CONSTRAINT statutory_rules_rule_type_check CHECK (rule_type IN ('tax', 'pension', 'health', 'levy', 'other'))");
        $connection->statement("ALTER TABLE statutory_rules ADD CONSTRAINT statutory_rules_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE statutory_rules ADD CONSTRAINT statutory_rules_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        $connection->statement('ALTER TABLE statutory_brackets ADD CONSTRAINT statutory_brackets_amounts_check CHECK ((min_amount IS NULL OR min_amount >= 0) AND (max_amount IS NULL OR max_amount >= 0) AND (max_amount IS NULL OR min_amount IS NULL OR max_amount >= min_amount) AND (employee_rate IS NULL OR employee_rate >= 0) AND (employer_rate IS NULL OR employer_rate >= 0) AND (fixed_amount IS NULL OR fixed_amount >= 0))');
        $connection->statement("ALTER TABLE employee_statutory_profiles ADD CONSTRAINT employee_statutory_profiles_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE employee_statutory_profiles ADD CONSTRAINT employee_statutory_profiles_dates_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        $connection->statement('ALTER TABLE loan_types ADD CONSTRAINT loan_types_default_interest_rate_check CHECK (default_interest_rate >= 0)');
        $connection->statement("ALTER TABLE loan_types ADD CONSTRAINT loan_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE employee_loans ADD CONSTRAINT employee_loans_amounts_check CHECK (principal_amount > 0 AND interest_rate >= 0 AND (installment_amount IS NULL OR installment_amount > 0) AND (installment_count IS NULL OR installment_count > 0))');
        $connection->statement("ALTER TABLE employee_loans ADD CONSTRAINT employee_loans_status_check CHECK (status IN ('active', 'completed', 'cancelled', 'defaulted'))");
        $connection->statement('ALTER TABLE employee_loans ADD CONSTRAINT employee_loans_dates_check CHECK ((starts_on IS NULL OR starts_on >= issued_on) AND (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on))');
        $connection->statement("ALTER TABLE payroll_runs ADD CONSTRAINT payroll_runs_status_check CHECK (status IN ('draft', 'processing', 'approved', 'posted', 'cancelled'))");
        $connection->statement('CREATE UNIQUE INDEX payroll_runs_company_number_unique ON payroll_runs (company_id, run_number) WHERE branch_id IS NULL');
        $connection->statement('CREATE UNIQUE INDEX payroll_runs_branch_number_unique ON payroll_runs (branch_id, run_number) WHERE branch_id IS NOT NULL');
        $connection->statement("ALTER TABLE payroll_run_employees ADD CONSTRAINT payroll_run_employees_status_check CHECK (status IN ('draft', 'calculated', 'approved', 'posted'))");
        $connection->statement('ALTER TABLE payroll_run_employees ADD CONSTRAINT payroll_run_employees_amounts_check CHECK (gross_amount >= 0 AND taxable_amount >= 0 AND deduction_amount >= 0 AND net_amount >= 0)');
        $connection->statement('ALTER TABLE loan_repayments ADD CONSTRAINT loan_repayments_amount_check CHECK (amount > 0)');
        $connection->statement("ALTER TABLE loan_repayments ADD CONSTRAINT loan_repayments_status_check CHECK (status IN ('posted', 'reversed'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS payroll_runs_branch_number_unique');
        $connection->statement('DROP INDEX IF EXISTS payroll_runs_company_number_unique');

        $schema->dropIfExists('payslips');
        $schema->dropIfExists('payroll_run_employees');
        $schema->dropIfExists('payroll_runs');
        $schema->dropIfExists('loan_repayments');
        $schema->dropIfExists('employee_loans');
        $schema->dropIfExists('loan_types');
        $schema->dropIfExists('employee_statutory_profiles');
        $schema->dropIfExists('statutory_brackets');
        $schema->dropIfExists('statutory_rules');
        $schema->dropIfExists('employee_deductions');
        $schema->dropIfExists('employee_earnings');
        $schema->dropIfExists('deduction_types');
        $schema->dropIfExists('earning_types');
        $schema->dropIfExists('payroll_periods');
    }
};
