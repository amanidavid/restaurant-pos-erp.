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

        if ($schema->hasTable('purchase_orders')) {
            return;
        }

        $schema->create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('order_number', 40);
            $table->string('status', 24)->default('draft');
            $table->date('ordered_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'order_number'], 'purchase_orders_branch_order_number_unique');
            $table->index(['branch_id', 'status', 'ordered_on'], 'purchase_orders_branch_status_ordered_index');
            $table->index(['supplier_id', 'status'], 'purchase_orders_supplier_status_index');
        });

        $schema->create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestampsTz();

            $table->index(['purchase_order_id', 'sort_order'], 'purchase_order_items_order_sort_index');
            $table->index(['ingredient_id'], 'purchase_order_items_ingredient_index');
        });

        $schema->create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('received_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('receipt_number', 40);
            $table->string('status', 24)->default('draft');
            $table->date('received_on');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'receipt_number'], 'goods_receipts_branch_receipt_number_unique');
            $table->index(['branch_id', 'status', 'received_on'], 'goods_receipts_branch_status_received_index');
            $table->index(['warehouse_id', 'status'], 'goods_receipts_warehouse_status_index');
        });

        $schema->create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('line_total_cost', 18, 2)->default(0);
            $table->date('expires_on')->nullable();
            $table->timestampsTz();

            $table->index(['goods_receipt_id'], 'goods_receipt_items_receipt_index');
            $table->index(['ingredient_id'], 'goods_receipt_items_ingredient_index');
        });

        $schema->create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
            $table->string('batch_number', 80)->nullable();
            $table->date('received_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['ingredient_id', 'status'], 'stock_batches_ingredient_status_index');
            $table->index(['warehouse_id', 'status'], 'stock_batches_warehouse_status_index');
            $table->index(['warehouse_id', 'ingredient_id', 'status'], 'stock_batches_warehouse_ingredient_status_index');
            $table->index(['expires_on', 'status'], 'stock_batches_expires_status_index');
        });

        $schema->create('stock_movement_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120)->unique();
            $table->string('direction', 12);
            $table->boolean('is_system')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
        });

        $schema->create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('adjustment_number', 40);
            $table->string('reason_type', 24);
            $table->string('status', 24)->default('draft');
            $table->date('adjusted_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'adjustment_number'], 'stock_adjustments_branch_number_unique');
            $table->index(['branch_id', 'status', 'adjusted_on'], 'stock_adjustments_branch_status_adjusted_index');
            $table->index(['warehouse_id', 'status'], 'stock_adjustments_warehouse_status_index');
        });

        $schema->create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['stock_adjustment_id'], 'stock_adjustment_items_adjustment_index');
            $table->index(['ingredient_id'], 'stock_adjustment_items_ingredient_index');
        });

        $schema->create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('transfer_number', 40);
            $table->string('status', 24)->default('draft');
            $table->date('requested_on')->nullable();
            $table->date('dispatched_on')->nullable();
            $table->date('received_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['from_branch_id', 'transfer_number'], 'stock_transfers_from_branch_number_unique');
            $table->index(['from_branch_id', 'status', 'requested_on'], 'stock_transfers_from_branch_status_requested_index');
            $table->index(['to_branch_id', 'status', 'requested_on'], 'stock_transfers_to_branch_status_requested_index');
            $table->index(['from_warehouse_id', 'status'], 'stock_transfers_from_warehouse_status_index');
            $table->index(['to_warehouse_id', 'status'], 'stock_transfers_to_warehouse_status_index');
        });

        $schema->create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity_requested', 18, 4);
            $table->decimal('quantity_dispatched', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->timestampsTz();

            $table->index(['stock_transfer_id'], 'stock_transfer_items_transfer_index');
            $table->index(['ingredient_id'], 'stock_transfer_items_ingredient_index');
        });

        $schema->create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('counted_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('count_number', 40);
            $table->string('status', 24)->default('draft');
            $table->date('counted_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'count_number'], 'stock_counts_branch_number_unique');
            $table->index(['branch_id', 'status', 'counted_on'], 'stock_counts_branch_status_counted_index');
            $table->index(['warehouse_id', 'status'], 'stock_counts_warehouse_status_index');
        });

        $schema->create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('system_quantity', 18, 4)->default(0);
            $table->decimal('counted_quantity', 18, 4)->default(0);
            $table->decimal('variance_quantity', 18, 4)->default(0);
            $table->timestampsTz();

            $table->index(['stock_count_id'], 'stock_count_items_count_index');
            $table->index(['ingredient_id'], 'stock_count_items_ingredient_index');
        });

        $schema->create('waste_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('recorded_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('waste_number', 40);
            $table->string('status', 24)->default('draft');
            $table->date('recorded_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'waste_number'], 'waste_records_branch_number_unique');
            $table->index(['branch_id', 'status', 'recorded_on'], 'waste_records_branch_status_recorded_index');
            $table->index(['warehouse_id', 'status'], 'waste_records_warehouse_status_index');
        });

        $schema->create('waste_record_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('waste_record_id')->constrained('waste_records')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->text('reason')->nullable();
            $table->timestampsTz();

            $table->index(['waste_record_id'], 'waste_record_items_record_index');
            $table->index(['ingredient_id'], 'waste_record_items_ingredient_index');
        });

        $schema->create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('stock_movement_type_id')->constrained('stock_movement_types')->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
            $table->foreignId('stock_adjustment_item_id')->nullable()->constrained('stock_adjustment_items')->nullOnDelete();
            $table->foreignId('stock_transfer_item_id')->nullable()->constrained('stock_transfer_items')->nullOnDelete();
            $table->foreignId('stock_count_item_id')->nullable()->constrained('stock_count_items')->nullOnDelete();
            $table->foreignId('waste_record_item_id')->nullable()->constrained('waste_record_items')->nullOnDelete();
            $table->foreignId('performed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->timestampTz('moved_at');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['ingredient_id', 'moved_at'], 'stock_movements_ingredient_moved_index');
            $table->index(['warehouse_id', 'stock_movement_type_id', 'moved_at'], 'stock_movements_warehouse_type_moved_index');
            $table->index(['stock_batch_id', 'moved_at'], 'stock_movements_batch_moved_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'partially_received', 'received', 'cancelled'))");
        $connection->statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_amounts_check CHECK (subtotal_amount >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND total_amount >= 0)');
        $connection->statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_dates_check CHECK (expected_on IS NULL OR ordered_on IS NULL OR expected_on >= ordered_on)');
        $connection->statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_values_check CHECK (quantity > 0 AND unit_price >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND line_total >= 0 AND sort_order > 0)');
        $connection->statement("ALTER TABLE goods_receipts ADD CONSTRAINT goods_receipts_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        $connection->statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT goods_receipt_items_values_check CHECK (quantity_received > 0 AND unit_cost >= 0 AND line_total_cost >= 0)');
        $connection->statement("ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_status_check CHECK (status IN ('active', 'consumed', 'expired', 'closed'))");
        $connection->statement('ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_values_check CHECK (quantity_on_hand >= 0 AND unit_cost >= 0)');
        $connection->statement("ALTER TABLE stock_movement_types ADD CONSTRAINT stock_movement_types_direction_check CHECK (direction IN ('in', 'out'))");
        $connection->statement("ALTER TABLE stock_movement_types ADD CONSTRAINT stock_movement_types_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_values_check CHECK (quantity > 0 AND unit_cost >= 0)');
        $connection->statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_single_source_check CHECK (num_nonnulls(goods_receipt_item_id, stock_adjustment_item_id, stock_transfer_item_id, stock_count_item_id, waste_record_item_id) = 1)');
        $connection->statement("ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_reason_type_check CHECK (reason_type IN ('damage', 'expiry', 'count_variance', 'manual_correction', 'other'))");
        $connection->statement("ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_status_check CHECK (status IN ('draft', 'approved', 'posted', 'cancelled'))");
        $connection->statement('ALTER TABLE stock_adjustment_items ADD CONSTRAINT stock_adjustment_items_values_check CHECK (quantity_delta <> 0 AND unit_cost >= 0)');
        $connection->statement("ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_status_check CHECK (status IN ('draft', 'approved', 'dispatched', 'received', 'cancelled'))");
        $connection->statement('ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_dates_check CHECK ((dispatched_on IS NULL OR requested_on IS NULL OR dispatched_on >= requested_on) AND (received_on IS NULL OR dispatched_on IS NULL OR received_on >= dispatched_on))');
        $connection->statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT stock_transfer_items_values_check CHECK (quantity_requested > 0 AND quantity_dispatched >= 0 AND quantity_received >= 0)');
        $connection->statement("ALTER TABLE stock_counts ADD CONSTRAINT stock_counts_status_check CHECK (status IN ('draft', 'counted', 'posted', 'cancelled'))");
        $connection->statement('ALTER TABLE stock_count_items ADD CONSTRAINT stock_count_items_values_check CHECK (system_quantity >= 0 AND counted_quantity >= 0)');
        $connection->statement("ALTER TABLE waste_records ADD CONSTRAINT waste_records_status_check CHECK (status IN ('draft', 'posted', 'cancelled'))");
        $connection->statement('ALTER TABLE waste_record_items ADD CONSTRAINT waste_record_items_quantity_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('stock_movements');
        $schema->dropIfExists('waste_record_items');
        $schema->dropIfExists('waste_records');
        $schema->dropIfExists('stock_count_items');
        $schema->dropIfExists('stock_counts');
        $schema->dropIfExists('stock_transfer_items');
        $schema->dropIfExists('stock_transfers');
        $schema->dropIfExists('stock_adjustment_items');
        $schema->dropIfExists('stock_adjustments');
        $schema->dropIfExists('stock_movement_types');
        $schema->dropIfExists('stock_batches');
        $schema->dropIfExists('goods_receipt_items');
        $schema->dropIfExists('goods_receipts');
        $schema->dropIfExists('purchase_order_items');
        $schema->dropIfExists('purchase_orders');
    }
};
