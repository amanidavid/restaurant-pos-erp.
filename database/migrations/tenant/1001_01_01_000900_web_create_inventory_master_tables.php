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

        if ($schema->hasTable('supplier_categories')) {
            return;
        }

        $schema->create('supplier_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'supplier_categories_company_name_unique');
        });

        $schema->create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('tin', 40)->nullable();
            $table->string('vrn', 40)->nullable();
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'suppliers_company_name_unique');
            $table->index(['company_id', 'status'], 'suppliers_company_status_index');
        });

        $schema->create('supplier_category_supplier', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_category_id')->constrained('supplier_categories')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['supplier_id', 'supplier_category_id'], 'supplier_category_supplier_unique');
            $table->index(['supplier_category_id', 'supplier_id'], 'supplier_category_supplier_category_supplier_index');
        });

        $schema->create('supplier_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('name', 160);
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['supplier_id', 'status', 'type'], 'supplier_contacts_supplier_status_type_index');
        });

        $schema->create('stock_units', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('conversion_factor', 18, 6);
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_issue_unit')->default(false);
            $table->boolean('is_count_unit')->default(false);
            $table->timestampsTz();

            $table->unique(['ingredient_id', 'unit_id'], 'stock_units_ingredient_unit_unique');
        });

        $schema->create('stock_item_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('default_purchase_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->foreignId('default_issue_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('minimum_stock_level', 18, 4)->nullable();
            $table->decimal('maximum_stock_level', 18, 4)->nullable();
            $table->decimal('reorder_level', 18, 4)->nullable();
            $table->boolean('is_stock_item')->default(false);
            $table->boolean('is_perishable')->default(false);
            $table->boolean('track_batches')->default(false);
            $table->boolean('allow_negative_stock')->default(false);
            $table->timestampsTz();

            $table->unique('ingredient_id', 'stock_item_settings_ingredient_unique');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE supplier_categories ADD CONSTRAINT supplier_categories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_status_check CHECK (status IN ('active', 'inactive', 'blocked'))");
        $connection->statement("ALTER TABLE supplier_contacts ADD CONSTRAINT supplier_contacts_type_check CHECK (type IN ('general', 'sales', 'accounts', 'support'))");
        $connection->statement("ALTER TABLE supplier_contacts ADD CONSTRAINT supplier_contacts_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("CREATE UNIQUE INDEX supplier_contacts_one_primary_per_type_unique ON supplier_contacts (supplier_id, type) WHERE is_primary = true AND status = 'active'");
        $connection->statement('ALTER TABLE stock_units ADD CONSTRAINT stock_units_conversion_factor_check CHECK (conversion_factor > 0)');
        $connection->statement("CREATE UNIQUE INDEX stock_units_one_purchase_unique ON stock_units (ingredient_id) WHERE is_purchase_unit = true");
        $connection->statement("CREATE UNIQUE INDEX stock_units_one_issue_unique ON stock_units (ingredient_id) WHERE is_issue_unit = true");
        $connection->statement("CREATE UNIQUE INDEX stock_units_one_count_unique ON stock_units (ingredient_id) WHERE is_count_unit = true");
        $connection->statement('ALTER TABLE stock_item_settings ADD CONSTRAINT stock_item_settings_levels_check CHECK ((minimum_stock_level IS NULL OR minimum_stock_level >= 0) AND (maximum_stock_level IS NULL OR maximum_stock_level >= 0) AND (reorder_level IS NULL OR reorder_level >= 0))');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('stock_item_settings');
        $schema->dropIfExists('stock_units');
        $schema->dropIfExists('supplier_contacts');
        $schema->dropIfExists('supplier_category_supplier');
        $schema->dropIfExists('suppliers');
        $schema->dropIfExists('supplier_categories');
    }
};
