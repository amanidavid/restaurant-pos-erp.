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

        if ($schema->hasTable('units_of_measure')) {
            return;
        }

        $schema->create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('symbol', 20)->nullable();
            $table->string('unit_type', 24);
            $table->decimal('base_factor', 18, 6)->default(1);
            $table->boolean('is_base_unit')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'units_of_measure_company_name_unique');
            $table->unique(['company_id', 'symbol'], 'units_of_measure_company_symbol_unique');
            $table->index(['company_id', 'unit_type', 'status'], 'units_of_measure_company_type_status_index');
        });

        $schema->create('tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('rate_percentage', 8, 4)->default(0);
            $table->boolean('is_inclusive')->default(false);
            $table->string('status', 24)->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'tax_codes_company_name_unique');
            $table->index(['company_id', 'status'], 'tax_codes_company_status_index');
        });

        $schema->create('menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'parent_id', 'name'], 'menu_categories_company_parent_name_unique');
            $table->index(['company_id', 'status', 'sort_order'], 'menu_categories_company_status_sort_index');
        });

        $schema->create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 120);
            $table->char('currency_code', 3);
            $table->boolean('is_default')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['company_id', 'branch_id', 'status'], 'price_lists_company_branch_status_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE units_of_measure ADD CONSTRAINT units_of_measure_unit_type_check CHECK (unit_type IN ('count', 'weight', 'volume', 'length', 'time', 'other'))");
        $connection->statement('ALTER TABLE units_of_measure ADD CONSTRAINT units_of_measure_base_factor_check CHECK (base_factor > 0)');
        $connection->statement("ALTER TABLE units_of_measure ADD CONSTRAINT units_of_measure_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("CREATE UNIQUE INDEX units_of_measure_one_base_unique ON units_of_measure (company_id, unit_type) WHERE is_base_unit = true");
        $connection->statement('ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_rate_check CHECK (rate_percentage >= 0)');
        $connection->statement("ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_dates_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        $connection->statement("ALTER TABLE menu_categories ADD CONSTRAINT menu_categories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE menu_categories ADD CONSTRAINT menu_categories_sort_order_check CHECK (sort_order > 0)');
        $connection->statement("ALTER TABLE price_lists ADD CONSTRAINT price_lists_status_check CHECK (status IN ('active', 'inactive', 'retired'))");
        $connection->statement('ALTER TABLE price_lists ADD CONSTRAINT price_lists_dates_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        $connection->statement("CREATE UNIQUE INDEX price_lists_name_scope_unique ON price_lists (company_id, COALESCE(branch_id, 0), name)");
        $connection->statement("CREATE UNIQUE INDEX price_lists_one_default_scope_unique ON price_lists (company_id, COALESCE(branch_id, 0)) WHERE is_default = true AND status = 'active'");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('price_lists');
        $schema->dropIfExists('menu_categories');
        $schema->dropIfExists('tax_codes');
        $schema->dropIfExists('units_of_measure');
    }
};
