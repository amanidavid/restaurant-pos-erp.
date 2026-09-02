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

        if ($schema->hasTable('kitchen_tickets')) {
            return;
        }

        $schema->create('kitchen_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('kitchen_section_id')->nullable()->constrained('kitchen_sections')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('ticket_number', 40);
            $table->string('status', 24)->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'ticket_number'], 'kitchen_tickets_branch_ticket_number_unique');
            $table->index(['branch_id', 'status', 'opened_at'], 'kitchen_tickets_branch_status_opened_index');
            $table->index(['kitchen_section_id', 'status'], 'kitchen_tickets_section_status_index');
        });

        $schema->create('kitchen_ticket_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('kitchen_ticket_id')->constrained('kitchen_tickets')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('item_name_snapshot', 160);
            $table->decimal('quantity', 18, 4);
            $table->string('status', 24)->default('queued');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampsTz();

            $table->unique(['kitchen_ticket_id', 'order_item_id'], 'kitchen_ticket_items_ticket_order_item_unique');
            $table->index(['order_item_id', 'status'], 'kitchen_ticket_items_order_item_status_index');
        });

        $schema->create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('reservation_number', 40);
            $table->string('customer_name_snapshot', 180);
            $table->string('customer_phone_snapshot', 20)->nullable();
            $table->unsignedInteger('guest_count');
            $table->timestampTz('reserved_from');
            $table->timestampTz('reserved_to')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'reservation_number'], 'reservations_branch_reservation_number_unique');
            $table->index(['branch_id', 'status', 'reserved_from'], 'reservations_branch_status_from_index');
            $table->index(['customer_id', 'reserved_from'], 'reservations_customer_from_index');
        });

        $schema->create('reservation_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['reservation_id', 'dining_table_id'], 'reservation_tables_reservation_table_unique');
            $table->index(['dining_table_id'], 'reservation_tables_table_index');
        });

        $schema->create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('number_sequence_id')->nullable()->constrained('number_sequences')->nullOnDelete();
            $table->foreignId('issued_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->string('status', 24)->default('issued');
            $table->decimal('total_amount', 18, 2);
            $table->timestampTz('issued_at');
            $table->timestampsTz();

            $table->index(['order_id', 'status'], 'receipts_order_status_index');
            $table->index(['status', 'issued_at'], 'receipts_status_issued_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE kitchen_tickets ADD CONSTRAINT kitchen_tickets_status_check CHECK (status IN ('open', 'preparing', 'ready', 'completed', 'cancelled'))");
        $connection->statement('ALTER TABLE kitchen_tickets ADD CONSTRAINT kitchen_tickets_dates_check CHECK (completed_at IS NULL OR completed_at >= opened_at)');
        $connection->statement("ALTER TABLE kitchen_ticket_items ADD CONSTRAINT kitchen_ticket_items_status_check CHECK (status IN ('queued', 'preparing', 'ready', 'served', 'cancelled'))");
        $connection->statement('ALTER TABLE kitchen_ticket_items ADD CONSTRAINT kitchen_ticket_items_values_check CHECK (quantity > 0)');
        $connection->statement('ALTER TABLE kitchen_ticket_items ADD CONSTRAINT kitchen_ticket_items_dates_check CHECK (ready_at IS NULL OR started_at IS NULL OR ready_at >= started_at)');
        $connection->statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'))");
        $connection->statement('ALTER TABLE reservations ADD CONSTRAINT reservations_values_check CHECK (guest_count > 0)');
        $connection->statement('ALTER TABLE reservations ADD CONSTRAINT reservations_dates_check CHECK (reserved_to IS NULL OR reserved_to >= reserved_from)');
        $connection->statement("ALTER TABLE receipts ADD CONSTRAINT receipts_status_check CHECK (status IN ('issued', 'voided'))");
        $connection->statement('ALTER TABLE receipts ADD CONSTRAINT receipts_total_amount_check CHECK (total_amount >= 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('receipts');
        $schema->dropIfExists('reservation_tables');
        $schema->dropIfExists('reservations');
        $schema->dropIfExists('kitchen_ticket_items');
        $schema->dropIfExists('kitchen_tickets');
    }
};
