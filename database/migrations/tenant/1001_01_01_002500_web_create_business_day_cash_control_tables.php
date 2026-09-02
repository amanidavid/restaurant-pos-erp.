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

        if ($schema->hasTable('business_day_cash_reconciliations')) {
            return;
        }

        $schema->create('business_day_cash_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_date_id')->constrained('business_dates')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('opened_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('closed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('reconciliation_number', 50);
            $table->decimal('opening_float_amount', 18, 2)->default(0);
            $table->decimal('cash_sales_amount', 18, 2)->default(0);
            $table->decimal('cash_refund_amount', 18, 2)->default(0);
            $table->decimal('cash_in_amount', 18, 2)->default(0);
            $table->decimal('cash_out_amount', 18, 2)->default(0);
            $table->decimal('safe_drop_amount', 18, 2)->default(0);
            $table->decimal('expected_cash_amount', 18, 2)->default(0);
            $table->decimal('counted_cash_amount', 18, 2)->nullable();
            $table->decimal('variance_amount', 18, 2)->nullable();
            $table->string('status', 24)->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['business_date_id', 'cash_account_id'], 'business_day_cash_recon_date_account_unique');
            $table->unique(['branch_id', 'reconciliation_number'], 'business_day_cash_recon_branch_number_unique');
            $table->index(['branch_id', 'status', 'opened_at'], 'business_day_cash_recon_branch_status_opened_index');
            $table->index(['cash_account_id', 'status'], 'business_day_cash_recon_account_status_index');
        });

        $schema->create('shift_cash_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shift_session_id')->constrained('shift_sessions')->cascadeOnDelete();
            $table->foreignId('business_date_id')->nullable()->constrained('business_dates')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('opened_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('closed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('reconciliation_number', 50);
            $table->decimal('opening_float_amount', 18, 2)->default(0);
            $table->decimal('cash_sales_amount', 18, 2)->default(0);
            $table->decimal('cash_refund_amount', 18, 2)->default(0);
            $table->decimal('cash_in_amount', 18, 2)->default(0);
            $table->decimal('cash_out_amount', 18, 2)->default(0);
            $table->decimal('safe_drop_amount', 18, 2)->default(0);
            $table->decimal('expected_cash_amount', 18, 2)->default(0);
            $table->decimal('counted_cash_amount', 18, 2)->nullable();
            $table->decimal('variance_amount', 18, 2)->nullable();
            $table->string('status', 24)->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['shift_session_id', 'cash_account_id'], 'shift_cash_recon_shift_account_unique');
            $table->unique(['branch_id', 'reconciliation_number'], 'shift_cash_recon_branch_number_unique');
            $table->index(['business_date_id', 'status'], 'shift_cash_recon_business_date_status_index');
            $table->index(['branch_id', 'status', 'opened_at'], 'shift_cash_recon_branch_status_opened_index');
            $table->index(['cash_account_id', 'status'], 'shift_cash_recon_account_status_index');
        });

        $schema->create('cash_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_day_cash_reconciliation_id')
                ->nullable()
                ->constrained('business_day_cash_reconciliations')
                ->cascadeOnDelete();
            $table->foreignId('shift_cash_reconciliation_id')
                ->nullable()
                ->constrained('shift_cash_reconciliations')
                ->cascadeOnDelete();
            $table->decimal('denomination_value', 18, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_amount', 18, 2);
            $table->timestampsTz();

            $table->unique(
                ['business_day_cash_reconciliation_id', 'denomination_value'],
                'cash_count_lines_business_recon_denomination_unique'
            );
            $table->unique(
                ['shift_cash_reconciliation_id', 'denomination_value'],
                'cash_count_lines_shift_recon_denomination_unique'
            );
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE business_day_cash_reconciliations ADD CONSTRAINT business_day_cash_recon_status_check CHECK (status IN ('open', 'closing', 'closed', 'approved'))");
        $connection->statement('ALTER TABLE business_day_cash_reconciliations ADD CONSTRAINT business_day_cash_recon_dates_check CHECK (closed_at IS NULL OR closed_at >= opened_at)');
        $connection->statement('ALTER TABLE business_day_cash_reconciliations ADD CONSTRAINT business_day_cash_recon_amounts_check CHECK (opening_float_amount >= 0 AND cash_sales_amount >= 0 AND cash_refund_amount >= 0 AND cash_in_amount >= 0 AND cash_out_amount >= 0 AND safe_drop_amount >= 0 AND expected_cash_amount >= 0 AND (counted_cash_amount IS NULL OR counted_cash_amount >= 0))');
        $connection->statement("ALTER TABLE shift_cash_reconciliations ADD CONSTRAINT shift_cash_recon_status_check CHECK (status IN ('open', 'closing', 'closed', 'approved'))");
        $connection->statement('ALTER TABLE shift_cash_reconciliations ADD CONSTRAINT shift_cash_recon_dates_check CHECK (closed_at IS NULL OR closed_at >= opened_at)');
        $connection->statement('ALTER TABLE shift_cash_reconciliations ADD CONSTRAINT shift_cash_recon_amounts_check CHECK (opening_float_amount >= 0 AND cash_sales_amount >= 0 AND cash_refund_amount >= 0 AND cash_in_amount >= 0 AND cash_out_amount >= 0 AND safe_drop_amount >= 0 AND expected_cash_amount >= 0 AND (counted_cash_amount IS NULL OR counted_cash_amount >= 0))');
        $connection->statement('ALTER TABLE cash_count_lines ADD CONSTRAINT cash_count_lines_values_check CHECK (denomination_value > 0 AND quantity > 0 AND line_amount = denomination_value * quantity)');
        $connection->statement('ALTER TABLE cash_count_lines ADD CONSTRAINT cash_count_lines_owner_check CHECK (num_nonnulls(business_day_cash_reconciliation_id, shift_cash_reconciliation_id) = 1)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('cash_count_lines');
        $schema->dropIfExists('shift_cash_reconciliations');
        $schema->dropIfExists('business_day_cash_reconciliations');
    }
};
