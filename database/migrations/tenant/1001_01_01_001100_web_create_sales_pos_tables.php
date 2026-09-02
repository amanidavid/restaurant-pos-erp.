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

        if ($schema->hasTable('orders')) {
            return;
        }

        $schema->create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'customer_groups_company_name_unique');
        });

        $schema->create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->string('name', 180);
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['company_id', 'status'], 'customers_company_status_index');
            $table->index(['customer_group_id', 'status'], 'customers_group_status_index');
        });

        $schema->create('sales_channels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 24);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'sales_channels_company_name_unique');
            $table->index(['company_id', 'type', 'status'], 'sales_channels_company_type_status_index');
        });

        $schema->create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 24);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'service_types_company_name_unique');
            $table->index(['company_id', 'type', 'status'], 'service_types_company_type_status_index');
        });

        $schema->create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('discount_type', 24);
            $table->decimal('value', 18, 4);
            $table->decimal('maximum_amount', 18, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'discounts_company_name_unique');
            $table->index(['company_id', 'status'], 'discounts_company_status_index');
        });

        $schema->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('business_date_id')->nullable()->constrained('business_dates')->nullOnDelete();
            $table->foreignId('shift_session_id')->nullable()->constrained('shift_sessions')->nullOnDelete();
            $table->foreignId('counter_id')->nullable()->constrained('counters')->nullOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sales_channel_id')->nullable()->constrained('sales_channels')->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained('service_types')->nullOnDelete();
            $table->foreignId('opened_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('closed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('guest_count')->default(1);
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('service_charge_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('tip_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status', 'opened_at'], 'orders_branch_status_opened_index');
            $table->index(['business_date_id', 'status'], 'orders_business_date_status_index');
            $table->index(['customer_id', 'opened_at'], 'orders_customer_opened_index');
        });

        $schema->create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('branch_menu_item_id')->constrained('branch_menu_items')->restrictOnDelete();
            $table->foreignId('parent_order_item_id')->nullable()->constrained('order_items')->cascadeOnDelete();
            $table->string('item_name_snapshot', 160);
            $table->decimal('unit_price_snapshot', 18, 2);
            $table->unsignedSmallInteger('preparation_minutes_snapshot')->default(0);
            $table->decimal('quantity', 18, 4);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->string('status', 24)->default('new');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['order_id', 'status'], 'order_items_order_status_index');
            $table->index(['branch_menu_item_id', 'status'], 'order_items_branch_menu_status_index');
        });

        $schema->create('order_item_modifiers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('modifier_option_id')->constrained('modifier_options')->restrictOnDelete();
            $table->string('modifier_name_snapshot', 120);
            $table->decimal('price_delta_snapshot', 18, 2)->default(0);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestampsTz();

            $table->index(['order_item_id'], 'order_item_modifiers_item_index');
        });

        $schema->create('order_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('changed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->timestampTz('changed_at');
            $table->timestampsTz();

            $table->index(['order_id', 'changed_at'], 'order_status_logs_order_changed_index');
        });

        $schema->create('discount_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained('discounts')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('discount_name_snapshot', 120);
            $table->string('discount_type_snapshot', 24);
            $table->decimal('value_snapshot', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['order_id'], 'discount_applications_order_index');
        });

        $schema->create('service_charges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('charge_type', 24);
            $table->decimal('value', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->timestampsTz();
        });

        $schema->create('tips', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE customer_groups ADD CONSTRAINT customer_groups_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('active', 'inactive', 'blocked'))");
        $connection->statement("ALTER TABLE sales_channels ADD CONSTRAINT sales_channels_type_check CHECK (type IN ('pos', 'online', 'aggregator', 'phone', 'manual'))");
        $connection->statement("ALTER TABLE sales_channels ADD CONSTRAINT sales_channels_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE service_types ADD CONSTRAINT service_types_type_check CHECK (type IN ('dine_in', 'takeaway', 'delivery', 'pickup'))");
        $connection->statement("ALTER TABLE service_types ADD CONSTRAINT service_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE discounts ADD CONSTRAINT discounts_type_check CHECK (discount_type IN ('percentage', 'fixed'))");
        $connection->statement("ALTER TABLE discounts ADD CONSTRAINT discounts_status_check CHECK (status IN ('active', 'inactive', 'expired'))");
        $connection->statement('ALTER TABLE discounts ADD CONSTRAINT discounts_values_check CHECK (value >= 0 AND (maximum_amount IS NULL OR maximum_amount >= 0))');
        $connection->statement('ALTER TABLE discounts ADD CONSTRAINT discounts_dates_check CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from)');
        $connection->statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('draft', 'open', 'sent_to_kitchen', 'partially_ready', 'ready', 'served', 'cancelled', 'closed'))");
        $connection->statement('ALTER TABLE orders ADD CONSTRAINT orders_amounts_check CHECK (guest_count > 0 AND subtotal_amount >= 0 AND discount_amount >= 0 AND service_charge_amount >= 0 AND tax_amount >= 0 AND tip_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0)');
        $connection->statement('ALTER TABLE orders ADD CONSTRAINT orders_dates_check CHECK (closed_at IS NULL OR closed_at >= opened_at)');
        $connection->statement("ALTER TABLE order_items ADD CONSTRAINT order_items_status_check CHECK (status IN ('new', 'sent', 'preparing', 'ready', 'served', 'cancelled', 'voided'))");
        $connection->statement('ALTER TABLE order_items ADD CONSTRAINT order_items_amounts_check CHECK (unit_price_snapshot >= 0 AND preparation_minutes_snapshot >= 0 AND quantity > 0 AND discount_amount >= 0 AND tax_amount >= 0 AND line_total >= 0)');
        $connection->statement('ALTER TABLE order_item_modifiers ADD CONSTRAINT order_item_modifiers_values_check CHECK (quantity > 0 AND line_total >= 0)');
        $connection->statement('ALTER TABLE discount_applications ADD CONSTRAINT discount_applications_values_check CHECK (value_snapshot >= 0 AND amount >= 0)');
        $connection->statement("ALTER TABLE service_charges ADD CONSTRAINT service_charges_type_check CHECK (charge_type IN ('percentage', 'fixed'))");
        $connection->statement('ALTER TABLE service_charges ADD CONSTRAINT service_charges_values_check CHECK (value >= 0 AND amount >= 0)');
        $connection->statement('ALTER TABLE tips ADD CONSTRAINT tips_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('tips');
        $schema->dropIfExists('service_charges');
        $schema->dropIfExists('discount_applications');
        $schema->dropIfExists('order_status_logs');
        $schema->dropIfExists('order_item_modifiers');
        $schema->dropIfExists('order_items');
        $schema->dropIfExists('orders');
        $schema->dropIfExists('discounts');
        $schema->dropIfExists('service_types');
        $schema->dropIfExists('sales_channels');
        $schema->dropIfExists('customers');
        $schema->dropIfExists('customer_groups');
    }
};
