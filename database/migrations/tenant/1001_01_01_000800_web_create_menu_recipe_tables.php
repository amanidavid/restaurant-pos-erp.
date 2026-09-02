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

        if ($schema->hasTable('menu_items')) {
            return;
        }

        $schema->create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('menu_category_id')->nullable()->constrained('menu_categories')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('sku', 60)->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_open_price')->default(false);
            $table->boolean('is_combo')->default(false);
            $table->string('image_url', 500)->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'menu_items_company_name_unique');
            $table->index(['company_id', 'is_active'], 'menu_items_company_active_index');
            $table->index(['menu_category_id', 'is_active'], 'menu_items_category_active_index');
        });

        $schema->create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('sku', 60)->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'ingredients_company_name_unique');
            $table->index(['company_id', 'is_active'], 'ingredients_company_active_index');
        });

        $schema->create('kitchen_sections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'kitchen_sections_branch_name_unique');
            $table->index(['branch_id', 'status', 'sort_order'], 'kitchen_sections_branch_status_sort_index');
        });

        $schema->create('branch_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('kitchen_section_id')->nullable()->constrained('kitchen_sections')->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->unsignedSmallInteger('preparation_minutes')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestampsTz();

            $table->unique(['branch_id', 'menu_item_id'], 'branch_menu_items_branch_item_unique');
            $table->index(['branch_id', 'is_available', 'is_visible'], 'branch_menu_items_branch_availability_index');
            $table->index(['kitchen_section_id', 'is_available'], 'branch_menu_items_kitchen_availability_index');
        });

        $schema->create('modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('min_select')->default(0);
            $table->unsignedSmallInteger('max_select')->default(1);
            $table->boolean('is_required')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'modifier_groups_company_name_unique');
        });

        $schema->create('modifier_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('price_delta', 18, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestampsTz();

            $table->unique(['modifier_group_id', 'name'], 'modifier_options_group_name_unique');
            $table->index(['modifier_group_id', 'is_active', 'sort_order'], 'modifier_options_group_active_sort_index');
        });

        $schema->create('menu_item_modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['menu_item_id', 'modifier_group_id'], 'menu_item_modifier_groups_item_group_unique');
            $table->index(['modifier_group_id', 'is_active'], 'menu_item_modifier_groups_group_active_index');
        });

        $schema->create('menu_item_prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->decimal('price_amount', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestampsTz();

            $table->index(['price_list_id', 'is_active'], 'menu_item_prices_list_active_index');
        });

        $schema->create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('yield_quantity', 18, 4)->default(1);
            $table->foreignId('yield_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['menu_item_id'], 'recipes_item_unique');
        });

        $schema->create('recipe_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('waste_percentage', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestampsTz();

            $table->unique(['recipe_id', 'ingredient_id'], 'recipe_ingredients_recipe_ingredient_unique');
            $table->index(['recipe_id', 'sort_order'], 'recipe_ingredients_recipe_sort_index');
        });

        $schema->create('combo_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        $schema->create('combo_product_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('combo_product_id')->constrained('combo_products')->cascadeOnDelete();
            $table->foreignId('component_menu_item_id')->constrained('menu_items')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestampsTz();

            $table->unique(['combo_product_id', 'component_menu_item_id'], 'combo_product_items_combo_component_unique');
            $table->index(['combo_product_id', 'sort_order'], 'combo_product_items_combo_sort_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('CREATE UNIQUE INDEX menu_items_company_sku_unique ON menu_items (company_id, sku) WHERE sku IS NOT NULL');
        $connection->statement('CREATE UNIQUE INDEX ingredients_company_sku_unique ON ingredients (company_id, sku) WHERE sku IS NOT NULL');
        $connection->statement("ALTER TABLE kitchen_sections ADD CONSTRAINT kitchen_sections_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE kitchen_sections ADD CONSTRAINT kitchen_sections_sort_order_check CHECK (sort_order > 0)');
        $connection->statement('ALTER TABLE branch_menu_items ADD CONSTRAINT branch_menu_items_values_check CHECK (price >= 0 AND cost_price >= 0 AND preparation_minutes >= 0 AND sort_order > 0)');
        $connection->statement('ALTER TABLE modifier_groups ADD CONSTRAINT modifier_groups_selection_check CHECK (min_select >= 0 AND max_select >= min_select)');
        $connection->statement("ALTER TABLE modifier_groups ADD CONSTRAINT modifier_groups_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE modifier_options ADD CONSTRAINT modifier_options_sort_order_check CHECK (sort_order > 0)');
        $connection->statement("CREATE UNIQUE INDEX modifier_options_one_default_unique ON modifier_options (modifier_group_id) WHERE is_default = true AND is_active = true");
        $connection->statement('ALTER TABLE menu_item_modifier_groups ADD CONSTRAINT menu_item_modifier_groups_sort_order_check CHECK (sort_order > 0)');
        $connection->statement('ALTER TABLE menu_item_prices ADD CONSTRAINT menu_item_prices_amount_check CHECK (price_amount >= 0)');
        $connection->statement('ALTER TABLE menu_item_prices ADD CONSTRAINT menu_item_prices_dates_check CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        $connection->statement("CREATE UNIQUE INDEX menu_item_prices_scope_unique ON menu_item_prices (price_list_id, menu_item_id)");
        $connection->statement('ALTER TABLE recipes ADD CONSTRAINT recipes_yield_quantity_check CHECK (yield_quantity > 0)');
        $connection->statement("CREATE UNIQUE INDEX recipes_one_active_unique ON recipes (menu_item_id) WHERE is_active = true");
        $connection->statement('ALTER TABLE recipe_ingredients ADD CONSTRAINT recipe_ingredients_quantity_check CHECK (quantity > 0)');
        $connection->statement('ALTER TABLE recipe_ingredients ADD CONSTRAINT recipe_ingredients_waste_percentage_check CHECK (waste_percentage >= 0 AND waste_percentage <= 100)');
        $connection->statement('ALTER TABLE recipe_ingredients ADD CONSTRAINT recipe_ingredients_sort_order_check CHECK (sort_order > 0)');
        $connection->statement("CREATE UNIQUE INDEX combo_products_item_unique ON combo_products (menu_item_id)");
        $connection->statement('ALTER TABLE combo_product_items ADD CONSTRAINT combo_product_items_quantity_check CHECK (quantity > 0)');
        $connection->statement('ALTER TABLE combo_product_items ADD CONSTRAINT combo_product_items_sort_order_check CHECK (sort_order > 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('combo_product_items');
        $schema->dropIfExists('combo_products');
        $schema->dropIfExists('recipe_ingredients');
        $schema->dropIfExists('recipes');
        $schema->dropIfExists('menu_item_prices');
        $schema->dropIfExists('menu_item_modifier_groups');
        $schema->dropIfExists('modifier_options');
        $schema->dropIfExists('modifier_groups');
        $schema->dropIfExists('branch_menu_items');
        $schema->dropIfExists('kitchen_sections');
        $schema->dropIfExists('ingredients');
        $schema->dropIfExists('menu_items');
    }
};
