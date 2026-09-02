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

        if ($schema->hasTable('employment_types') || $schema->hasColumn('tenant_users', 'employment_status')) {
            return;
        }

        $schema->create('employment_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_salary_based')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'employment_types_company_name_unique');
            $table->index(['company_id', 'status'], 'employment_types_company_status_index');
        });

        $schema->table('tenant_users', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->after('company_id')->constrained('employment_types')->nullOnDelete();
            $table->foreignId('home_branch_id')->nullable()->after('employment_type_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('home_branch_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->after('department_id')->constrained('job_positions')->nullOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->after('job_position_id')->constrained('cost_centres')->nullOnDelete();
            $table->string('employee_number', 40)->nullable()->after('display_name');
            $table->date('date_of_birth')->nullable()->after('employee_number');
            $table->date('hired_on')->nullable()->after('date_of_birth');
            $table->date('confirmed_on')->nullable()->after('hired_on');
            $table->date('ended_on')->nullable()->after('confirmed_on');
            $table->string('employment_status', 24)->default('active')->after('ended_on');
            $table->string('gender', 16)->nullable()->after('employment_status');
            $table->string('national_id_number', 60)->nullable()->after('gender');
            $table->string('tax_number', 60)->nullable()->after('national_id_number');
            $table->string('nssf_number', 60)->nullable()->after('tax_number');
            $table->string('nhif_number', 60)->nullable()->after('nssf_number');
        });

        $schema->create('tenant_user_branch_employments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['tenant_user_id', 'branch_id'], 'tenant_user_branch_employments_user_branch_unique');
            $table->index(['branch_id', 'status'], 'tenant_user_branch_employments_branch_status_index');
        });

        $schema->create('tenant_user_job_histories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained('job_positions')->nullOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->decimal('base_salary_amount', 18, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'effective_from'], 'tenant_user_job_histories_user_effective_index');
        });

        $schema->create('shift_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 120);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('crosses_midnight')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['company_id', 'branch_id', 'status'], 'shift_definitions_company_branch_status_index');
        });

        $schema->create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('shift_definition_id')->constrained('shift_definitions')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'status', 'valid_from'], 'shift_assignments_user_status_valid_index');
        });

        $schema->create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('shift_definition_id')->nullable()->constrained('shift_definitions')->nullOnDelete();
            $table->foreignId('shift_session_id')->nullable()->constrained('shift_sessions')->nullOnDelete();
            $table->date('attendance_date');
            $table->timestampTz('clock_in_at')->nullable();
            $table->timestampTz('clock_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status', 24)->default('present');
            $table->string('source', 24)->default('manual');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_user_id', 'attendance_date'], 'attendance_records_user_date_unique');
            $table->index(['branch_id', 'attendance_date'], 'attendance_records_branch_date_index');
            $table->index(['attendance_date', 'status'], 'attendance_records_date_status_index');
        });

        $schema->create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('is_paid')->default(true);
            $table->decimal('default_days', 8, 2)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'leave_types_company_name_unique');
        });

        $schema->create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days_requested', 8, 2);
            $table->string('status', 24)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'status'], 'leave_requests_user_status_index');
            $table->index(['status', 'starts_on'], 'leave_requests_status_start_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE employment_types ADD CONSTRAINT employment_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_employment_status_check CHECK (employment_status IN ('active', 'inactive', 'suspended', 'terminated'))");
        $connection->statement("ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_gender_check CHECK (gender IS NULL OR gender IN ('male', 'female', 'other'))");
        $connection->statement('ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_employment_dates_check CHECK (ended_on IS NULL OR hired_on IS NULL OR ended_on >= hired_on)');
        $connection->statement('CREATE UNIQUE INDEX tenant_users_company_employee_number_unique ON tenant_users (company_id, employee_number) WHERE company_id IS NOT NULL AND employee_number IS NOT NULL');
        $connection->statement("ALTER TABLE tenant_user_branch_employments ADD CONSTRAINT tenant_user_branch_employments_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE tenant_user_branch_employments ADD CONSTRAINT tenant_user_branch_employments_dates_check CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from)');
        $connection->statement("CREATE UNIQUE INDEX tenant_user_branch_employments_one_primary_unique ON tenant_user_branch_employments (tenant_user_id) WHERE is_primary = true AND status = 'active'");
        $connection->statement('ALTER TABLE tenant_user_job_histories ADD CONSTRAINT tenant_user_job_histories_salary_check CHECK (base_salary_amount IS NULL OR base_salary_amount >= 0)');
        $connection->statement('ALTER TABLE tenant_user_job_histories ADD CONSTRAINT tenant_user_job_histories_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        $connection->statement("CREATE UNIQUE INDEX tenant_user_job_histories_one_open_unique ON tenant_user_job_histories (tenant_user_id) WHERE effective_to IS NULL");
        $connection->statement("ALTER TABLE shift_definitions ADD CONSTRAINT shift_definitions_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE shift_definitions ADD CONSTRAINT shift_definitions_grace_check CHECK (grace_minutes >= 0)');
        $connection->statement("CREATE UNIQUE INDEX shift_definitions_name_scope_unique ON shift_definitions (company_id, COALESCE(branch_id, 0), name)");
        $connection->statement("ALTER TABLE shift_assignments ADD CONSTRAINT shift_assignments_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE shift_assignments ADD CONSTRAINT shift_assignments_dates_check CHECK (valid_to IS NULL OR valid_to >= valid_from)');
        $connection->statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status_check CHECK (status IN ('present', 'absent', 'leave', 'off', 'half_day'))");
        $connection->statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_source_check CHECK (source IN ('manual', 'device', 'import', 'system'))");
        $connection->statement('ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_minutes_check CHECK (worked_minutes >= 0 AND late_minutes >= 0 AND overtime_minutes >= 0)');
        $connection->statement('ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_times_check CHECK (clock_out_at IS NULL OR clock_in_at IS NULL OR clock_out_at >= clock_in_at)');
        $connection->statement("ALTER TABLE leave_types ADD CONSTRAINT leave_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE leave_types ADD CONSTRAINT leave_types_default_days_check CHECK (default_days IS NULL OR default_days >= 0)');
        $connection->statement("ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");
        $connection->statement('ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_dates_check CHECK (ends_on >= starts_on)');
        $connection->statement('ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_days_check CHECK (days_requested > 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $schema->dropIfExists('leave_requests');
        $schema->dropIfExists('leave_types');
        $schema->dropIfExists('attendance_records');
        $schema->dropIfExists('shift_assignments');
        $schema->dropIfExists('shift_definitions');
        $schema->dropIfExists('tenant_user_job_histories');
        $schema->dropIfExists('tenant_user_branch_employments');
        $schema->dropIfExists('employment_types');

        $connection->statement('DROP INDEX IF EXISTS tenant_users_company_employee_number_unique');
        $connection->statement('ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_employment_status_check');
        $connection->statement('ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_gender_check');
        $connection->statement('ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_employment_dates_check');

        $schema->table('tenant_users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('employment_type_id');
            $table->dropConstrainedForeignId('home_branch_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropConstrainedForeignId('cost_centre_id');
            $table->dropColumn([
                'employee_number',
                'date_of_birth',
                'hired_on',
                'confirmed_on',
                'ended_on',
                'employment_status',
                'gender',
                'national_id_number',
                'tax_number',
                'nssf_number',
                'nhif_number',
            ]);
        });
    }
};
