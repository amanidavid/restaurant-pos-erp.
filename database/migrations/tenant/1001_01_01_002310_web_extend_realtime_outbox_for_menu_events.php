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

        if (! $schema->hasTable('realtime_event_outbox')) {
            return;
        }

        if (! $schema->hasColumn('realtime_event_outbox', 'branch_menu_item_id')) {
            $schema->table('realtime_event_outbox', function (Blueprint $table): void {
                $table
                    ->foreignId('branch_menu_item_id')
                    ->nullable()
                    ->after('branch_device_id')
                    ->constrained('branch_menu_items')
                    ->cascadeOnDelete();
            });
        }

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('DROP INDEX IF EXISTS realtime_event_outbox_branch_menu_item_created_index');
        $connection->statement('CREATE INDEX realtime_event_outbox_branch_menu_item_created_index ON realtime_event_outbox (branch_menu_item_id, created_at)');
        $connection->statement('ALTER TABLE realtime_event_outbox DROP CONSTRAINT IF EXISTS realtime_event_outbox_type_check');
        $connection->statement("ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_type_check CHECK (event_type IN ('order.created', 'order.updated', 'order.cancelled', 'order.sent_to_kitchen', 'kitchen_ticket.created', 'kitchen_ticket.updated', 'kitchen_ticket.ready', 'reservation.created', 'reservation.updated', 'payment.completed', 'payment.voided', 'refund.requested', 'refund.approved', 'refund.completed', 'stock.updated', 'approval.requested', 'approval.decided', 'notification.created', 'print_job.created', 'print_job.updated', 'device.status_changed', 'menu_item.availability_changed'))");
        $connection->statement('ALTER TABLE realtime_event_outbox DROP CONSTRAINT IF EXISTS realtime_event_outbox_source_check');
        $connection->statement('ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_source_check CHECK (num_nonnulls(order_id, kitchen_ticket_id, reservation_id, payment_id, refund_id, stock_movement_id, approval_request_id, notification_message_id, print_job_id, branch_device_id, branch_menu_item_id) >= 1)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('realtime_event_outbox')) {
            return;
        }

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('DROP INDEX IF EXISTS realtime_event_outbox_branch_menu_item_created_index');
        $connection->statement('ALTER TABLE realtime_event_outbox DROP CONSTRAINT IF EXISTS realtime_event_outbox_type_check');
        $connection->statement("ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_type_check CHECK (event_type IN ('order.created', 'order.updated', 'order.cancelled', 'order.sent_to_kitchen', 'kitchen_ticket.created', 'kitchen_ticket.updated', 'kitchen_ticket.ready', 'reservation.created', 'reservation.updated', 'payment.completed', 'payment.voided', 'refund.requested', 'refund.approved', 'refund.completed', 'stock.updated', 'approval.requested', 'approval.decided', 'notification.created', 'print_job.created', 'print_job.updated', 'device.status_changed'))");
        $connection->statement('ALTER TABLE realtime_event_outbox DROP CONSTRAINT IF EXISTS realtime_event_outbox_source_check');
        $connection->statement('ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_source_check CHECK (num_nonnulls(order_id, kitchen_ticket_id, reservation_id, payment_id, refund_id, stock_movement_id, approval_request_id, notification_message_id, print_job_id, branch_device_id) >= 1)');

        if ($schema->hasColumn('realtime_event_outbox', 'branch_menu_item_id')) {
            $schema->table('realtime_event_outbox', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_menu_item_id');
            });
        }
    }
};
