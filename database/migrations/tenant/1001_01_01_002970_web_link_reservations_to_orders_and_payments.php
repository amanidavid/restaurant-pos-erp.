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

        if (! $schema->hasColumn('orders', 'reservation_id')) {
            $schema->table('orders', function (Blueprint $table): void {
                $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            });
        }

        $schema->table('payments', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->change();
            if (! Schema::connection(self::CONNECTION)->hasColumn('payments', 'reservation_id')) {
                $table->foreignId('reservation_id')->nullable()->constrained('reservations')->restrictOnDelete();
            }
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('CREATE INDEX IF NOT EXISTS orders_reservation_index ON orders (reservation_id)');
        $connection->statement('CREATE INDEX IF NOT EXISTS payments_reservation_status_index ON payments (reservation_id, status)');
        $connection->statement('CREATE INDEX IF NOT EXISTS reservations_branch_from_id_index ON reservations (branch_id, reserved_from, id)');
        $connection->statement('CREATE INDEX IF NOT EXISTS reservations_branch_number_prefix_index ON reservations (branch_id, reservation_number text_pattern_ops)');
        $connection->unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'payments_order_or_reservation_check' AND conrelid = 'payments'::regclass) THEN
                    ALTER TABLE payments ADD CONSTRAINT payments_order_or_reservation_check CHECK (num_nonnulls(order_id, reservation_id) >= 1);
            END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_order_or_reservation_check');
        $connection->statement('DROP INDEX IF EXISTS reservations_branch_number_prefix_index');
        $schema->table('reservations', fn (Blueprint $table) => $table->dropIndex('reservations_branch_from_id_index'));
        $schema->table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_reservation_status_index');
            $table->dropConstrainedForeignId('reservation_id');
            $table->foreignId('order_id')->nullable(false)->change();
        });
        $schema->table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_reservation_index');
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
