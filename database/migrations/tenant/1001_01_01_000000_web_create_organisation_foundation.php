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

        if ($schema->hasTable('companies')) {
            return;
        }

        $schema->create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('legal_name', 180);
            $table->string('trading_name', 180);
            $table->string('tin', 40)->nullable()->unique();
            $table->string('vrn', 40)->nullable()->unique();
            $table->char('country_code', 2);
            $table->string('timezone', 64);
            $table->string('locale', 16)->default('en');
            $table->char('base_currency_code', 3);
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        $schema->create('branches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('name_normalized', 160);
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address_line_1', 180)->nullable();
            $table->string('address_line_2', 180)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64);
            $table->char('currency_code', 3);
            $table->string('status', 24)->default('active');
            $table->date('opened_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'name_normalized'], 'branches_company_name_unique');
            $table->index(['status', 'name_normalized'], 'branches_status_name_index');
        });

        $schema->create('departments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        $schema->create('cost_centres', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cost_centres')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['branch_id', 'status'], 'cost_centres_branch_status_index');
        });

        $schema->create('locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('name', 140);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'parent_id', 'name'], 'locations_branch_parent_name_unique');
            $table->index(['branch_id', 'type', 'status'], 'locations_branch_type_status_index');
        });

        $schema->create('branch_configurations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->time('business_day_opens_at');
            $table->time('business_day_closes_at');
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_id', 'version'], 'branch_configurations_branch_version_unique');
            $table->index(
                ['branch_id', 'is_active', 'effective_from'],
                'branch_configurations_effective_index'
            );
        });

        $schema->create('business_dates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->date('business_date');
            $table->string('business_day_number', 40);
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('opened_by_id');
            $table->unsignedBigInteger('closed_by_id')->nullable();
            $table->timestampTz('planned_open_at')->nullable();
            $table->timestampTz('planned_close_at')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->decimal('opening_float_amount', 18, 2)->default(0);
            $table->decimal('expected_cash_amount', 18, 2)->default(0);
            $table->decimal('counted_cash_amount', 18, 2)->nullable();
            $table->decimal('closing_variance_amount', 18, 2)->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'business_date'], 'business_dates_branch_date_unique');
            $table->unique(['branch_id', 'business_day_number'], 'business_dates_branch_number_unique');
            $table->index(['branch_id', 'status'], 'business_dates_branch_status_index');
            $table->index(['branch_id', 'status', 'opened_at'], 'business_dates_branch_status_opened_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE companies ADD CONSTRAINT companies_status_check CHECK (status IN ('active', 'suspended', 'closed'))");
        $connection->statement("ALTER TABLE branches ADD CONSTRAINT branches_status_check CHECK (status IN ('active', 'inactive', 'closed'))");
        $connection->statement('ALTER TABLE branches ADD CONSTRAINT branches_latitude_check CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)');
        $connection->statement('ALTER TABLE branches ADD CONSTRAINT branches_longitude_check CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)');
        $connection->statement("ALTER TABLE business_dates ADD CONSTRAINT business_dates_status_check CHECK (status IN ('open', 'closing', 'closed'))");
        $connection->statement('ALTER TABLE business_dates ADD CONSTRAINT business_dates_planned_dates_check CHECK (planned_close_at IS NULL OR planned_open_at IS NULL OR planned_close_at >= planned_open_at)');
        $connection->statement('ALTER TABLE business_dates ADD CONSTRAINT business_dates_actual_dates_check CHECK (closed_at IS NULL OR closed_at >= opened_at)');
        $connection->statement('ALTER TABLE business_dates ADD CONSTRAINT business_dates_cash_amounts_check CHECK (opening_float_amount >= 0 AND expected_cash_amount >= 0 AND (counted_cash_amount IS NULL OR counted_cash_amount >= 0))');
        $connection->statement("CREATE UNIQUE INDEX business_dates_one_open_unique ON business_dates (branch_id) WHERE status IN ('open', 'closing')");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('business_dates');
        $schema->dropIfExists('branch_configurations');
        $schema->dropIfExists('locations');
        $schema->dropIfExists('cost_centres');
        $schema->dropIfExists('departments');
        $schema->dropIfExists('branches');
        $schema->dropIfExists('companies');
    }
};
